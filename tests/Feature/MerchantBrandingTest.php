<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\PaymentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MerchantBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base' => 'sandbox',
            ],
        ]);
    }

    public function test_get_display_name_prefers_qr_display_name_over_name_column(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Legal Name LLC',
            'qr_display_name' => 'Customer Facing Brand',
        ]);

        $this->assertSame('Customer Facing Brand', $merchant->getDisplayName());
    }

    public function test_get_display_name_falls_back_to_name_then_platform_default(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Only Name',
            'qr_display_name' => null,
        ]);

        $this->assertSame('Only Name', $merchant->getDisplayName());

        $blank = Merchant::factory()->create(['name' => '', 'qr_display_name' => null]);
        $this->assertSame(Merchant::DEFAULT_DISPLAY_NAME, $blank->getDisplayName());
    }

    public function test_get_theme_color_validates_hex_and_falls_back(): void
    {
        $merchant = Merchant::factory()->create(['theme_color' => '#FF00AB']);
        $this->assertSame('#FF00AB', $merchant->getThemeColor());

        $invalid = Merchant::factory()->create(['theme_color' => 'not-a-color']);
        $this->assertSame(Merchant::DEFAULT_THEME_COLOR, $invalid->getThemeColor());
    }

    public function test_get_logo_url_returns_default_when_no_file(): void
    {
        $merchant = Merchant::factory()->create(['logo_path' => null]);

        $this->assertStringContainsString('images/default-logo.svg', $merchant->getLogoUrl());
    }

    public function test_get_qr_merchant_name_truncates_to_64_characters(): void
    {
        $long = str_repeat('B', 80);
        $merchant = Merchant::factory()->create(['name' => $long]);

        $this->assertSame(64, mb_strlen($merchant->getQrMerchantName()));
        $this->assertSame(mb_substr($long, 0, 64), $merchant->getQrMerchantName());
    }

    public function test_payment_creation_sends_qr_code_merchant_name_from_branding(): void
    {
        Http::fake([
            '*' => function (\Illuminate\Http\Client\Request $request) {
                $body = json_decode($request->body(), true);
                $this->assertIsArray($body);
                $this->assertSame('Branded Cafe', $body['qrCodeMerchantName'] ?? null);

                return Http::response([
                    'status' => 0,
                    'data' => ['orderId' => 'ord-brand', 'qrCode' => 'qr'],
                ], 200);
            },
        ]);

        $user = User::factory()->create();
        $user->merchant->forceFill([
            'qr_display_name' => 'Branded Cafe',
        ])->save();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => Gateway::query()->where('code', 'coins')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $creationService = app(PaymentCreationService::class);
        $creationService->create($user->merchant, 'coins', [
            'amount' => 50,
            'currency' => 'PHP',
            'reference' => 'BRAND-REF-1',
        ]);

        Http::assertSentCount(1);
    }
}
