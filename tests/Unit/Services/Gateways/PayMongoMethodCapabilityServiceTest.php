<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Gateways\PayMongoMethodCapabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoMethodCapabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('paymongo.gateway.secret_key', 'sk_test_capabilities');
    }

    public function test_it_maps_paymongo_payment_method_capabilities_to_kemopay_gateway_codes(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => [
                    'attributes' => [
                        'payment_methods' => ['qrph', 'gcash', 'paymaya'],
                    ],
                ],
            ]),
        ]);

        $service = app(PayMongoMethodCapabilityService::class);

        $this->assertSame(['qrph', 'gcash', 'maya'], $service->availableGatewayCodes());
        $this->assertSame('available', $service->statusForGateway('gcash'));
        $this->assertSame('unavailable', $service->statusForGateway('grab_pay'));
        $this->assertSame('not_applicable', $service->statusForGateway('paypal'));
        $this->assertSame('verified', $service->summary()['status']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paymongo.com/v1/merchants/capabilities/payment_methods'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_capabilities:')));
    }

    public function test_it_caches_a_verified_capability_response(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => ['attributes' => ['payment_methods' => ['qrph']]],
            ]),
        ]);

        $service = app(PayMongoMethodCapabilityService::class);
        $service->availableGatewayCodes();
        $service->availableGatewayCodes();

        Http::assertSentCount(1);
    }

    public function test_it_reads_a_top_level_capability_list_and_shopee_pay_identifier(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'qrph',
                'gcash',
                'paymaya',
                'grab_pay',
                'shopee_pay',
            ]),
        ]);

        $service = app(PayMongoMethodCapabilityService::class);

        $this->assertSame(['qrph', 'gcash', 'maya', 'grab_pay', 'shopeepay'], $service->availableGatewayCodes());
        $this->assertSame('available', $service->statusForGateway('shopeepay'));
    }

    public function test_it_reports_unverified_when_the_capability_request_fails(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'errors' => [['code' => 'authentication_invalid']],
            ], 401),
        ]);

        $service = app(PayMongoMethodCapabilityService::class);

        $this->assertNull($service->availableGatewayCodes());
        $this->assertSame('unverified', $service->statusForGateway('qrph'));
        $this->assertSame('unverified', $service->summary()['status']);
    }
}
