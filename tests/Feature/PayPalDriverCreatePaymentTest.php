<?php

namespace Tests\Feature;

use App\Services\Gateways\Drivers\PaypalDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalDriverCreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_returns_approve_redirect_url(): void
    {
        config(['app.url' => 'http://gatewayhub.test']);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123'],
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123'],
                ],
            ], 201),
        ]);

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
        ]);

        $result = $driver->createPayment([
            'amount' => 199.99,
            'currency' => 'PHP',
            'reference' => 'ORDER-PAYPAL-001',
        ]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $payload = $request->data();
            $experienceContext = is_array($payload) ? (($payload['payment_source']['paypal']['experience_context'] ?? [])) : [];
            $experienceReturnUrl = is_array($experienceContext) ? ($experienceContext['return_url'] ?? null) : null;
            $experienceCancelUrl = is_array($experienceContext) ? ($experienceContext['cancel_url'] ?? null) : null;
            $userAction = is_array($experienceContext) ? ($experienceContext['user_action'] ?? null) : null;
            $hasLegacyContext = is_array($payload) && array_key_exists('application_context', $payload);

            return is_string($experienceReturnUrl)
                && is_string($experienceCancelUrl)
                && is_string($userAction)
                && str_ends_with($experienceReturnUrl, '/dashboard/payments')
                && str_ends_with($experienceCancelUrl, '/dashboard/payments')
                && $userAction === 'PAY_NOW'
                && $hasLegacyContext === false;
        });

        $this->assertSame('PAYPAL-ORDER-123', $result['external_payment_id']);
        $this->assertSame('PAYPAL-ORDER-123', $result['provider_reference']);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123', $result['redirect_url']);
        $this->assertIsArray($result['raw']);
    }

    public function test_create_payment_throws_when_credentials_missing(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('PayPal credentials are missing');

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => '',
            'client_secret' => '',
        ]);

        $driver->createPayment([
            'amount' => 10,
            'currency' => 'PHP',
            'reference' => 'ORDER-PAYPAL-LEGACY',
        ]);
    }

    public function test_create_payment_uses_fallback_redirect_when_config_redirects_are_blank(): void
    {
        config(['app.url' => 'http://gatewayhub.test']);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-BLANK-REDIRECT',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-BLANK-REDIRECT'],
                ],
            ], 201),
        ]);

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
            'redirect_success_url' => '   ',
            'redirect_cancel_url' => '',
        ]);

        $driver->createPayment([
            'amount' => 49.99,
            'currency' => 'PHP',
            'reference' => 'ORDER-PAYPAL-BLANK-REDIRECT',
        ]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $payload = $request->data();
            $experienceContext = is_array($payload) ? (($payload['payment_source']['paypal']['experience_context'] ?? [])) : [];
            $returnUrl = is_array($experienceContext) ? ($experienceContext['return_url'] ?? null) : null;
            $cancelUrl = is_array($experienceContext) ? ($experienceContext['cancel_url'] ?? null) : null;

            return is_string($returnUrl)
                && is_string($cancelUrl)
                && str_contains($returnUrl, '/dashboard/payments')
                && str_contains($cancelUrl, '/dashboard/payments');
        });
    }

    public function test_create_payment_throws_when_approve_link_missing(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-123'],
                ],
            ], 201),
        ]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('checkout redirect URL missing');

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
        ]);

        $driver->createPayment([
            'amount' => 99.99,
            'currency' => 'PHP',
            'reference' => 'ORDER-PAYPAL-002',
        ]);
    }

    public function test_create_payment_accepts_payer_action_link(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-PA',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-PA'],
                    ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-PA'],
                ],
            ], 201),
        ]);

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
        ]);

        $result = $driver->createPayment([
            'amount' => 50.00,
            'currency' => 'PHP',
            'reference' => 'ORDER-PAYPAL-PA-001',
        ]);

        $this->assertSame('PAYPAL-ORDER-PA', $result['external_payment_id']);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-PA', $result['redirect_url']);
    }

    public function test_get_payment_status_returns_order_details(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/*' => Http::response([
                'id' => 'PAYPAL-ORDER-XYZ',
                'status' => 'APPROVED',
            ], 200),
        ]);

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
        ]);

        $status = $driver->getPaymentStatus('PAYPAL-ORDER-XYZ');

        $this->assertSame('PAYPAL-ORDER-XYZ', $status['id'] ?? null);
        $this->assertSame('APPROVED', $status['status'] ?? null);
    }

    public function test_capture_payment_returns_capture_response(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-CAP',
                'status' => 'COMPLETED',
            ], 201),
        ]);

        $driver = new PaypalDriver([
            'api_base' => 'sandbox',
            'client_id' => 'paypal-client-id',
            'client_secret' => 'paypal-client-secret',
        ]);

        $capture = $driver->capturePayment('PAYPAL-ORDER-CAP');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v2/checkout/orders/PAYPAL-ORDER-CAP/capture')) {
                return false;
            }

            return trim($request->body()) === '{}';
        });

        $this->assertSame('PAYPAL-ORDER-CAP', $capture['id'] ?? null);
        $this->assertSame('COMPLETED', $capture['status'] ?? null);
    }
}
