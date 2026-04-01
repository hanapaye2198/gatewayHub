<?php

namespace Tests\Unit;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantQrCodeMerchantNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_name_accessor_trims_name_column(): void
    {
        $merchant = Merchant::factory()->create(['name' => '  Shop Co  ']);

        $this->assertSame('Shop Co', $merchant->business_name);
    }

    public function test_normalize_qr_code_merchant_name_falls_back_when_blank(): void
    {
        $this->assertSame('GatewayHub Merchant', Merchant::normalizeQrCodeMerchantName(null));
        $this->assertSame('GatewayHub Merchant', Merchant::normalizeQrCodeMerchantName('   '));
        $this->assertSame('Acme', Merchant::normalizeQrCodeMerchantName('Acme'));
    }

    public function test_qr_code_merchant_display_name_uses_fallback_when_name_empty(): void
    {
        $merchant = Merchant::factory()->create(['name' => '']);

        $this->assertNull($merchant->business_name);
        $this->assertSame('GatewayHub Merchant', $merchant->qrCodeMerchantDisplayName());
    }
}
