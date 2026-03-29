<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard.payments'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_merchant_sees_own_payments(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'ORDER-123',
            'gateway_code' => 'coins',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => 'paid',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments'));

        $response->assertOk();
        $response->assertSee('ORDER-123');
        $response->assertSee('500.00');
        $response->assertSee('PHP');
        $response->assertSee('Paid');
    }

    public function test_merchant_does_not_see_other_merchants_payments(): void
    {
        $merchant = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherPayment = Payment::factory()->for($otherUser->merchant)->create([
            'reference_id' => 'OTHER-REF',
            'status' => 'paid',
        ]);

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.payments'));

        $response->assertOk();
        $response->assertDontSee('OTHER-REF');
    }

    public function test_status_badges_display_correctly(): void
    {
        $user = User::factory()->create();
        Payment::factory()->for($user->merchant)->create(['status' => 'pending', 'reference_id' => 'PENDING-1']);
        Payment::factory()->for($user->merchant)->paid()->create(['reference_id' => 'PAID-1']);
        Payment::factory()->for($user->merchant)->failed()->create(['reference_id' => 'FAILED-1']);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments'));

        $response->assertOk();
        $response->assertSee('PENDING-1');
        $response->assertSee('PAID-1');
        $response->assertSee('FAILED-1');
        $response->assertSee('Pending');
        $response->assertSee('Paid');
        $response->assertSee('Failed');
    }

    public function test_empty_state_when_no_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard.payments'));

        $response->assertOk();
        $response->assertSee(__('No payments yet.'));
    }

    public function test_qr_payment_shows_waiting_state_when_pending(): void
    {
        Gateway::create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
        ]);
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'QR-ORDER',
            'gateway_code' => 'coins',
            'status' => 'pending',
            'raw_response' => ['data' => ['qrCode' => '00020126...']],
        ]);

        $response = Livewire::actingAs($user)->test('pages::dashboard.payments')
            ->call('selectPayment', $payment->id);

        $response->assertSet('showPaymentDetail', true);
        $response->assertOk();
        $html = $response->html();
        $this->assertTrue(
            str_contains($html, 'Scan with GCash, Maya, Coins wallet, or other QRPH-compatible apps.')
            || str_contains($html, 'QR code unavailable'),
            'Expected pending QR payment to show either scan instructions or unavailable message'
        );
    }

    public function test_maya_labeled_payment_shows_qr_waiting_state_when_pending(): void
    {
        Gateway::create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => \App\Services\Gateways\Drivers\MayaDriver::class,
            'is_global_enabled' => true,
        ]);
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'MAYA-QR-ORDER',
            'gateway_code' => 'maya',
            'status' => 'pending',
            'raw_response' => ['data' => ['qrCode' => '000201010212...']],
        ]);

        $response = Livewire::actingAs($user)->test('pages::dashboard.payments')
            ->call('selectPayment', $payment->id);

        $response->assertSet('showPaymentDetail', true);
        $response->assertOk();
        $html = $response->html();
        $this->assertTrue(
            str_contains($html, 'Scan with GCash, Maya, Coins wallet, or other QRPH-compatible apps.')
            || str_contains($html, 'QR code unavailable'),
            'Expected Maya-labeled pending payment to show scan instructions or unavailable message'
        );
        $response->assertDontSee('Open checkout');
    }

    public function test_qr_payment_shows_paid_state_when_paid(): void
    {
        Gateway::create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
        ]);
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->paid()->create([
            'reference_id' => 'QR-PAID',
            'gateway_code' => 'coins',
            'raw_response' => ['data' => ['qrCode' => '00020126...']],
        ]);

        $this->actingAs($user);
        $response = Livewire::test('pages::dashboard.payments')
            ->call('selectPayment', $payment->id);

        $response->assertSee('Paid');
        $response->assertDontSee('Waiting for payment');
    }

    public function test_payment_detail_page_displays_payment_info_for_pending_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'DETAIL-REF',
            'gateway_code' => 'coins',
            'amount' => 100.50,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('DETAIL-REF');
        $response->assertSee('100.50');
        $response->assertSee('PHP');
        $response->assertSee('Pending');
        $response->assertDontSee('Audit Timeline');
        $response->assertDontSee('SurePay Sending Logs');
        $response->assertDontSee('SurePay Settlement Logs');
    }

    public function test_payment_detail_page_redirects_to_dashboard_for_paid_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->paid()->create([
            'reference_id' => 'DETAIL-PAID-REF',
            'gateway_code' => 'paypal',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments.show', $payment));

        $response->assertRedirect(route('dashboard.payments'));
    }

    public function test_payment_detail_returns_404_for_other_merchant_payment(): void
    {
        $merchant = User::factory()->create();
        $otherUser = User::factory()->create();
        $payment = Payment::factory()->for($otherUser->merchant)->create(['reference_id' => 'OTHER-REF']);

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.payments.show', $payment));

        $response->assertNotFound();
    }

    public function test_payment_detail_hides_webhook_and_tunnel_logs_for_merchant(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'AUDIT-REF',
            'gateway_code' => 'coins',
            'status' => 'pending',
            'raw_response' => [
                'surepay_sending_logs' => [
                    ['status' => 'queued', 'stage' => 'gross_transfer_recorded', 'logged_at' => now()->toIso8601String()],
                ],
                'surepay_wallet_errors' => [
                    ['message' => 'Sample tunnel error', 'logged_at' => now()->toIso8601String()],
                ],
            ],
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertDontSee('Webhook events for this payment');
        $response->assertDontSee('SurePay Sending Logs');
        $response->assertDontSee('SurePay Settlement Logs');
    }

    public function test_payment_detail_client_script_redirects_to_payments_dashboard_on_success(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->for($user->merchant)->create([
            'reference_id' => 'REDIRECT-REF',
            'gateway_code' => 'paypal',
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('paymentsUrl:', false);
        $response->assertSee('window.location.href = this.paymentsUrl;', false);
    }

    public function test_dashboard_payments_list_keeps_pending_status_until_webhook_updates_payment(): void
    {
        Http::fake();

        $merchant = User::factory()->create();
        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'PAYPAL-ORDER-LIST-1',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments'));
        $response->assertOk();
        $response->assertSee('Pending');

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        Http::assertNothingSent();
    }

    public function test_dashboard_payments_filters_by_status_date_and_reference(): void
    {
        $merchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-FILTER-MATCH-001',
            'provider_reference' => 'M-PROV-MATCH-001',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-02-12 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-12 10:00:00'),
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-FILTER-PENDING-002',
            'provider_reference' => 'M-PROV-PENDING-002',
            'status' => 'pending',
            'created_at' => Carbon::parse('2026-02-12 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-12 10:00:00'),
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-FILTER-OLD-003',
            'provider_reference' => 'M-PROV-OLD-003',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-01-10 10:00:00'),
            'updated_at' => Carbon::parse('2026-01-10 10:00:00'),
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments', [
            'status' => 'paid',
            'reference' => 'MATCH',
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertSee('M-FILTER-MATCH-001');
        $response->assertDontSee('M-FILTER-PENDING-002');
        $response->assertDontSee('M-FILTER-OLD-003');
    }

    public function test_dashboard_payments_summary_cards_render_filtered_metrics(): void
    {
        $merchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-SUMMARY-PAID-001',
            'status' => 'paid',
            'amount' => 111.11,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-SUMMARY-PAID-002',
            'status' => 'paid',
            'amount' => 322.22,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-SUMMARY-PENDING-003',
            'status' => 'pending',
            'amount' => 50,
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments', [
            'status' => 'paid',
        ]));

        $response->assertOk();
        $response->assertSee('Total transactions');
        $response->assertSee('Paid collections');
        $response->assertSee('Pending count');
        $response->assertSee('Failed or refunded');
        $response->assertSee('PHP 433.33');
    }

    public function test_merchant_can_export_filtered_payments_and_export_is_isolated_to_own_records(): void
    {
        $merchant = User::factory()->create();
        $otherMerchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-EXPORT-PAID-001',
            'provider_reference' => 'M-EXPORT-PROV-001',
            'status' => 'paid',
            'amount' => 200,
            'created_at' => Carbon::parse('2026-02-14 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-14 10:00:00'),
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'M-EXPORT-PENDING-002',
            'provider_reference' => 'M-EXPORT-PROV-002',
            'status' => 'pending',
            'amount' => 210,
            'created_at' => Carbon::parse('2026-02-14 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-14 10:00:00'),
        ]);
        Payment::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'reference_id' => 'OTHER-EXPORT-PAID-003',
            'provider_reference' => 'OTHER-EXPORT-PROV-003',
            'status' => 'paid',
            'amount' => 999,
            'created_at' => Carbon::parse('2026-02-14 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-14 10:00:00'),
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.export', [
            'status' => 'paid',
            'reference' => 'EXPORT',
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('merchant-payments-', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $csvWithoutBom = substr($csv, 3);

        $rows = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($csvWithoutBom)) ?: [],
            static fn (string $line): bool => $line !== ''
        ));

        $this->assertCount(2, $rows);

        $header = str_getcsv($rows[0]);
        $this->assertSame([
            'Created At',
            'Reference',
            'Provider Reference',
            'Gateway',
            'Amount',
            'Currency',
            'Platform Fee',
            'Net Amount',
            'Status',
        ], $header);

        $data = str_getcsv($rows[1]);
        $this->assertSame('2026-02-14 10:00:00', $data[0] ?? null);
        $this->assertSame('M-EXPORT-PAID-001', $data[1] ?? null);
        $this->assertSame('200.00', $data[4] ?? null);
        $this->assertSame('paid', $data[8] ?? null);

        $this->assertStringNotContainsString('M-EXPORT-PENDING-002', $csvWithoutBom);
        $this->assertStringNotContainsString('OTHER-EXPORT-PAID-003', $csvWithoutBom);
    }

    public function test_merchant_export_sanitizes_formula_like_cells(): void
    {
        $merchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => '=INJECT-001',
            'provider_reference' => '@RAW-002',
            'status' => 'paid',
            'amount' => 120,
            'created_at' => Carbon::parse('2026-02-14 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-14 10:00:00'),
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $csvWithoutBom = substr($csv, 3);
        $rows = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($csvWithoutBom)) ?: [],
            static fn (string $line): bool => $line !== ''
        ));
        $this->assertCount(2, $rows);

        $data = str_getcsv($rows[1]);
        $this->assertSame("'=INJECT-001", $data[1] ?? null);
        $this->assertSame("'@RAW-002", $data[2] ?? null);
    }

    public function test_dashboard_payments_token_query_does_not_trigger_external_sync(): void
    {
        Http::fake();

        $merchant = User::factory()->create();
        $targetPayment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'PAYPAL-RETURN-TOKEN-1',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments').'?token=PAYPAL-RETURN-TOKEN-1');
        $response->assertOk();

        $targetPayment->refresh();
        $this->assertSame('pending', $targetPayment->status);
        $this->assertNull($targetPayment->paid_at);
        Http::assertNothingSent();
    }
}
