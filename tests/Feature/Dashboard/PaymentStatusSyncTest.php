<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_status_poll_keeps_pending_without_external_gateway_sync(): void
    {
        Http::fake();

        $merchant = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $merchant->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'PAYPAL-ORDER-123',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->actingAs($merchant)->getJson(route('dashboard.payments.status', $payment));

        $response->assertOk()->assertJson(['status' => 'pending']);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        Http::assertNothingSent();
    }

    public function test_dashboard_status_poll_marks_expired_pending_payment_as_failed(): void
    {
        Http::fake();

        $merchant = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $merchant->id,
            'gateway_code' => 'coins',
            'status' => 'pending',
            'raw_response' => [
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $response = $this->actingAs($merchant)->getJson(route('dashboard.payments.status', $payment));

        $response->assertOk()->assertJson(['status' => 'failed']);

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        Http::assertNothingSent();
    }

    public function test_dashboard_status_poll_reconciles_pending_coins_payment_from_provider_status(): void
    {
        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'prod-client',
                'client_secret' => 'prod-secret',
                'api_base' => 'prod',
            ],
        ]);

        Http::fake([
            'api.pro.coins.ph/openapi/fiat/v1/get_qr_code*' => Http::response([
                'status' => 0,
                'error' => 'OK',
                'data' => [
                    'requestId' => 'GH-STATUS-001',
                    'referenceId' => '2179969337375674286',
                    'status' => 'SUCCEEDED',
                    'settleDate' => '1774608656000',
                    'cashInBank' => 'GCash',
                    'channelInvoiceNo' => '199195',
                ],
            ], 200),
        ]);

        $merchant = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $merchant->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'GH-STATUS-001',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'gateway_request_reference' => 'GH-STATUS-001',
                'merchant_reference' => 'DASH-TEST-001',
                'data' => [
                    'requestId' => 'GH-STATUS-001',
                    'status' => 'PENDING',
                ],
            ],
        ]);

        $response = $this->actingAs($merchant)->getJson(route('dashboard.payments.status', $payment));

        $response->assertOk()->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1774608656, $payment->paid_at->timestamp);
    }

    public function test_payment_detail_page_does_not_auto_redirect_pending_payment_without_webhook_update(): void
    {
        Http::fake();

        $merchant = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $merchant->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'PAYPAL-ORDER-125',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertSee($payment->reference_id);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        Http::assertNothingSent();
    }
}
