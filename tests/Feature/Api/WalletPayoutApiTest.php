<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessPayMongoTransferWebhookJob;
use App\Jobs\ProcessWebhookJob;
use App\Models\MerchantPayout;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletPayoutApiTest extends TestCase
{
    use RefreshDatabase;

    private int $batchTransferResponseStatus = 200;

    /** @var array<string, mixed> */
    private array $batchTransferResponse = [
        'data' => [
            'id' => 'btr_fixture',
            'transfers' => [[
                'id' => 'tr_fixture',
                'status' => 'pending',
                'provider' => 'instapay',
                'amount' => 20050,
                'fee' => 800,
                'currency' => 'PHP',
                'reference_number' => 'KPTESTREFERENCE',
                'provider_reference_number' => null,
            ]],
        ],
    ];

    /** @var array<string, mixed> */
    private array $walletListResponse = [
        'data' => [[
            'id' => 'wallet_fixture',
            'status' => 'activated',
            'account' => [
                'account_number' => '0000000001',
                'account_name' => 'KemoPay Test Account',
            ],
        ]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
        config()->set('paymongo.gateway.secret_key', 'sk_test_fixture');
        config()->set('paymongo.gateway.webhook_secret', 'whsk_test_fixture');
        config()->set('paymongo.payouts.source_account_number', '0000000001');
        config()->set('paymongo.payouts.source_account_name', 'KemoPay Test Account');
        config()->set('paymongo.payouts.callback_url', '');

        Http::fake([
            'https://api.paymongo.com/v1/wallets/receiving_institutions*' => Http::response([
                'data' => [[
                    'name' => 'Sample Bank',
                    'provider_code' => 'BNORPHMM',
                ]],
            ]),
            'https://api.paymongo.com/v2/batch_transfers' => fn () => Http::response(
                $this->batchTransferResponse,
                $this->batchTransferResponseStatus
            ),
            'https://api.paymongo.com/v2/wallets*' => fn () => Http::response($this->walletListResponse),
            'https://api.paymongo.com/v2/transfers*' => Http::response(['data' => []]),
        ]);
    }

    public function test_merchant_can_create_a_paymongo_payout_from_net_wallet_balance(): void
    {
        [$merchantToken, $merchant] = $this->createMerchant();
        $clearingWallet = $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_CLEARING, 150.00);
        $realWallet = $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 342.50);

        $response = $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), [
                'Idempotency-Key' => 'merchant-order-1001',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payout.status', 'pending')
            ->assertJsonPath('data.payout.amount', 200.5)
            ->assertJsonPath('data.payout.provider_fee', 8)
            ->assertJsonPath('data.payout.destination_account.number', '••••••7890');

        $this->assertSame(0.0, (float) $clearingWallet->fresh()->balance);
        $this->assertSame(292.0, (float) $realWallet->fresh()->balance);
        $this->assertSame(2, WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_MERCHANT_PAYOUT)
            ->where('direction', 'debit')
            ->count());

        Http::assertSent(function (ClientRequest $request): bool {
            $transfer = $request['transfers'][0] ?? [];

            return $request->url() === 'https://api.paymongo.com/v2/batch_transfers'
                && $request->hasHeader('Idempotency-Key')
                && ($transfer['amount'] ?? null) === 20050
                && ($transfer['currency'] ?? null) === 'PHP'
                && ($transfer['source_account']['bic'] ?? null) === 'PAEYPHM2XXX'
                && ($transfer['destination_account']['number'] ?? null) === '1234567890';
        });
    }

    public function test_payout_institutions_are_fetched_from_the_paymongo_transfers_endpoint(): void
    {
        [$merchantToken] = $this->createMerchant();

        $this->withToken($merchantToken)
            ->getJson('/api/wallets/payout-institutions?provider=instapay')
            ->assertOk()
            ->assertJsonPath('data.provider', 'instapay')
            ->assertJsonPath('data.institutions.0.provider_code', 'BNORPHMM');

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.paymongo.com/v1/wallets/receiving_institutions?provider=instapay');
    }

    public function test_paymongo_source_wallet_account_is_discovered_when_not_configured(): void
    {
        config()->set('paymongo.payouts.source_account_number', '');
        config()->set('paymongo.payouts.source_account_name', '');
        [$merchantToken, $merchant] = $this->createMerchant();
        $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 300.00);

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), [
                'Idempotency-Key' => 'merchant-order-wallet-discovery',
            ])
            ->assertCreated();

        Http::assertSent(fn (ClientRequest $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v2/wallets'
            && str_contains($request->url(), 'status=activated')
            && str_contains($request->url(), 'fields=account'));
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.paymongo.com/v2/batch_transfers'
            && ($request['transfers'][0]['source_account']['number'] ?? null) === '0000000001'
            && ($request['transfers'][0]['source_account']['name'] ?? null) === 'KemoPay Test Account');
    }

    public function test_insufficient_balance_does_not_submit_a_paymongo_transfer(): void
    {
        [$merchantToken, $merchant] = $this->createMerchant();
        $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_CLEARING, 10.00);

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), [
                'Idempotency-Key' => 'merchant-order-too-large',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('merchant_payouts', 0);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/v2/batch_transfers'));
    }

    public function test_instapay_limit_is_enforced_before_reserving_merchant_funds(): void
    {
        [$merchantToken, $merchant] = $this->createMerchant();
        $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 60000.00);
        $payload = $this->payoutPayload();
        $payload['amount'] = 50000.01;

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $payload, [
                'Idempotency-Key' => 'merchant-order-over-instapay-limit',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('merchant_payouts', 0);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/v2/batch_transfers'));
    }

    public function test_idempotency_key_reuses_the_original_payout_and_rejects_a_different_payload(): void
    {
        [$merchantToken, $merchant] = $this->createMerchant();
        $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 500.00);
        $headers = ['Idempotency-Key' => 'merchant-order-idempotent'];

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), $headers)
            ->assertCreated();

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), $headers)
            ->assertCreated();

        $changedPayload = $this->payoutPayload();
        $changedPayload['amount'] = 201.00;
        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $changedPayload, $headers)
            ->assertStatus(409);

        $this->assertDatabaseCount('merchant_payouts', 1);
        Http::assertSentCount(1);
    }

    public function test_confirmed_failed_transfer_releases_reserved_wallet_funds_only_once(): void
    {
        [$merchantToken, $merchant] = $this->createMerchant();
        $wallet = $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 500.00);

        $response = $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), [
                'Idempotency-Key' => 'merchant-order-failure',
            ])
            ->assertCreated();

        $payoutId = $response->json('data.payout.id');
        $referenceNumber = $response->json('data.payout.reference_number');
        $this->assertSame(299.5, (float) $wallet->fresh()->balance);

        $webhookPayload = [
            'data' => [
                'id' => 'evt_fixture_failure',
                'attributes' => [
                    'type' => 'transfer.outward.failed',
                    'livemode' => false,
                    'data' => [
                        'id' => 'tr_fixture',
                        'type' => 'wallet_transaction',
                        'attributes' => [
                            'status' => 'failed',
                            'transfer_id' => 'tr_fixture',
                            'reference_number' => $referenceNumber,
                            'fee' => 800,
                            'provider_error_code' => 'BENEFICIARY_ACCOUNT_INVALID',
                            'provider_error' => 'The beneficiary account could not be credited',
                        ],
                    ],
                ],
            ],
        ];

        $payoutService = app(WalletPayoutService::class);
        $payoutService->processWebhook($webhookPayload);
        $payoutService->processWebhook($webhookPayload);

        $this->assertSame(500.0, (float) $wallet->fresh()->balance);
        $this->assertSame('failed', MerchantPayout::query()->findOrFail($payoutId)->status);
        $this->assertSame(1, WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_MERCHANT_PAYOUT_REVERSAL)
            ->where('metadata->payout_id', $payoutId)
            ->count());
    }

    public function test_uncertain_paymongo_response_keeps_funds_reserved_and_reuses_the_same_provider_request(): void
    {
        $this->batchTransferResponseStatus = 500;
        $this->batchTransferResponse = [
            'errors' => [[
                'code' => 'internal_error',
                'detail' => 'The transfer status is not yet known.',
            ]],
        ];
        [$merchantToken, $merchant] = $this->createMerchant();
        $wallet = $this->createWallet($merchant->id, Wallet::TYPE_MERCHANT_REAL, 500.00);
        $headers = ['Idempotency-Key' => 'merchant-order-uncertain'];

        $firstResponse = $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), $headers)
            ->assertCreated();

        $firstResponse->assertJsonPath('data.payout.status', 'submission_unknown');
        $this->assertSame(299.5, (float) $wallet->fresh()->balance);

        $this->withToken($merchantToken)
            ->postJson('/api/wallets/payouts', $this->payoutPayload(), $headers)
            ->assertCreated()
            ->assertJsonPath('data.payout.status', 'submission_unknown');

        Http::assertSentCount(1);
    }

    public function test_verified_paymongo_outward_transfer_events_are_dispatched_to_the_payout_job(): void
    {
        Queue::fake();
        $timestamp = time();
        $payload = [
            'data' => [
                'id' => 'evt_fixture_success',
                'type' => 'event',
                'attributes' => [
                    'type' => 'transfer.outward.successful',
                    'livemode' => false,
                    'created_at' => $timestamp,
                    'data' => [
                        'id' => 'tr_fixture',
                        'type' => 'wallet_transaction',
                        'attributes' => [
                            'status' => 'succeeded',
                            'reference_number' => 'KPTESTREFERENCE',
                        ],
                    ],
                ],
            ],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_fixture');

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $rawBody)->assertOk();

        Queue::assertPushed(ProcessPayMongoTransferWebhookJob::class, fn (ProcessPayMongoTransferWebhookJob $job): bool => $job->payload === $payload);
        Queue::assertNotPushed(ProcessWebhookJob::class);
    }

    public function test_merchants_cannot_read_another_merchants_payout(): void
    {
        [$merchantToken] = $this->createMerchant();
        [, $otherMerchant] = $this->createMerchant();
        $payout = MerchantPayout::factory()->create(['merchant_id' => $otherMerchant->id]);

        $this->withToken($merchantToken)
            ->getJson('/api/wallets/payouts/'.$payout->id)
            ->assertNotFound();
    }

    /** @return array{string, \App\Models\Merchant} */
    private function createMerchant(): array
    {
        $merchantToken = Str::random(64);
        $user = User::factory()->withMerchantApiKey($merchantToken)->create();

        return [$merchantToken, $user->merchant];
    }

    private function createWallet(int $merchantId, string $walletType, float $balance): Wallet
    {
        return Wallet::factory()->create([
            'merchant_id' => $merchantId,
            'wallet_type' => $walletType,
            'currency' => 'PHP',
            'balance' => $balance,
        ]);
    }

    /** @return array<string, mixed> */
    private function payoutPayload(): array
    {
        return [
            'provider' => 'instapay',
            'amount' => 200.50,
            'currency' => 'PHP',
            'destination_account' => [
                'number' => '1234 567 890',
                'name' => 'Juan Dela Cruz',
                'bic' => 'BNORPHMM',
                'bank_name' => 'Sample Bank',
            ],
        ];
    }
}
