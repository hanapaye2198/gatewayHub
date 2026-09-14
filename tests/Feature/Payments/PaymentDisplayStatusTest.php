<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDisplayStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_list_shows_expired_for_provider_expired_webhook(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'reference_id' => 'EXPIRED-UI-001',
            'raw_response' => ['status' => 'EXPIRED'],
        ]);
        WebhookEvent::query()->create([
            'provider' => 'coins',
            'event_id' => 'expired-ui-001',
            'payment_id' => $payment->id,
            'payload' => ['status' => 'EXPIRED', 'referenceId' => $payment->provider_reference],
            'headers' => [],
            'received_at' => now(),
            'processed_at' => now(),
            'status' => 'processed',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments'));

        $response->assertOk();
        $response->assertSee('EXPIRED-UI-001');
        $response->assertSee('Expired');
        $response->assertDontSee('Payment QR expired because the customer did not complete the payment', false);
    }

    public function test_merchant_detail_explains_expired_qr_and_not_failed(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'reference_id' => 'EXPIRED-DETAIL-001',
            'raw_response' => ['status' => 'EXPIRED'],
        ]);
        WebhookEvent::query()->create([
            'provider' => 'coins',
            'event_id' => 'expired-detail-001',
            'payment_id' => $payment->id,
            'payload' => ['status' => 'EXPIRED'],
            'headers' => [],
            'received_at' => now(),
            'processed_at' => now(),
            'status' => 'processed',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('Expired');
        $response->assertSee('Payment QR expired because the customer did not complete the payment within the allowed time.');
        $response->assertSee('Create a new payment if the customer still wants to pay.');
        $response->assertDontSee('This payment did not complete due to a payment or processing failure.');
    }

    public function test_merchant_detail_explains_genuine_failed_without_qr_expiration_copy(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'reference_id' => 'FAILED-DETAIL-001',
            'raw_response' => ['status' => 'FAILED'],
        ]);
        WebhookEvent::query()->create([
            'provider' => 'coins',
            'event_id' => 'failed-detail-001',
            'payment_id' => $payment->id,
            'payload' => ['status' => 'FAILED'],
            'headers' => [],
            'received_at' => now(),
            'processed_at' => now(),
            'status' => 'processed',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('Failed');
        $response->assertSee('This payment did not complete due to a payment or processing failure.');
        $response->assertDontSee('Payment QR expired because the customer did not complete the payment');
    }

    public function test_merchant_expired_filter_hides_genuine_failed_payments(): void
    {
        $user = User::factory()->create();
        Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'reference_id' => 'FILTER-EXPIRED-ROW',
            'raw_response' => ['status' => 'EXPIRED'],
        ]);
        Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'reference_id' => 'FILTER-FAILED-ROW',
            'raw_response' => ['status' => 'FAILED'],
        ]);

        $expired = $this->actingAs($user)->get(route('dashboard.payments', ['status' => 'expired']));
        $expired->assertOk();
        $expired->assertSee('FILTER-EXPIRED-ROW');
        $expired->assertDontSee('FILTER-FAILED-ROW');

        $failed = $this->actingAs($user)->get(route('dashboard.payments', ['status' => 'failed']));
        $failed->assertOk();
        $failed->assertSee('FILTER-FAILED-ROW');
        $failed->assertDontSee('FILTER-EXPIRED-ROW');
    }

    public function test_api_status_contract_still_returns_failed_for_provider_expired(): void
    {
        $user = User::factory()->withMerchantApiKey('key-expired-display')->create();
        $payment = Payment::factory()->failed()->create([
            'merchant_id' => $user->merchant_id,
            'raw_response' => ['status' => 'EXPIRED'],
        ]);

        $response = $this->getJson('/api/payments/'.$payment->id.'/status', [
            'Authorization' => 'Bearer key-expired-display',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'failed');
        $this->assertArrayNotHasKey('display_status', $response->json('data'));
    }
}
