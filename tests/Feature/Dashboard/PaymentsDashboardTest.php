<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $payment = Payment::factory()->for($user)->create([
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
        $otherPayment = Payment::factory()->for($otherUser)->create([
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
        Payment::factory()->for($user)->create(['status' => 'pending', 'reference_id' => 'PENDING-1']);
        Payment::factory()->for($user)->paid()->create(['reference_id' => 'PAID-1']);
        Payment::factory()->for($user)->failed()->create(['reference_id' => 'FAILED-1']);

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
        $payment = Payment::factory()->for($user)->create([
            'reference_id' => 'QR-ORDER',
            'gateway_code' => 'coins',
            'status' => 'pending',
            'raw_response' => ['data' => ['qrCode' => '00020126...']],
        ]);

        $this->actingAs($user);
        $response = Livewire::test('pages::dashboard.payments')
            ->call('selectPayment', $payment->id);

        $response->assertSet('showPaymentDetail', true);
        $response->assertOk();
        $html = $response->html();
        $this->assertTrue(
            str_contains($html, 'Waiting for payment') || str_contains($html, 'QR code unavailable'),
            'Expected pending QR payment to show either QR with waiting message or unavailable message'
        );
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
        $payment = Payment::factory()->for($user)->paid()->create([
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
}
