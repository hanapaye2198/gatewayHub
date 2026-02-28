<?php

namespace Tests\Feature\Dashboard;

use App\Models\Payment;
use App\Models\User;
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
