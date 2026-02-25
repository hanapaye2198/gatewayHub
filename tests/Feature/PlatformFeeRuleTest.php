<?php

namespace Tests\Feature;

use App\Models\PlatformFeeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_global_rule_exists_after_migration(): void
    {
        $rule = PlatformFeeRule::query()->where('scope_type', 'global')->first();

        $this->assertNotNull($rule);
        $this->assertNull($rule->scope_id);
        $this->assertSame('percentage', $rule->fee_type);
        $this->assertSame('0.0150', (string) $rule->fee_value); // 1.5% from config
        $this->assertTrue($rule->is_active);
        $this->assertNotNull($rule->effective_from);
        $this->assertNull($rule->effective_to);
    }

    public function test_rule_can_be_created_with_all_scope_types(): void
    {
        $rule = PlatformFeeRule::query()->create([
            'scope_type' => 'merchant_gateway',
            'scope_id' => 1,
            'fee_type' => 'flat',
            'fee_value' => 2.5000,
            'is_active' => true,
            'effective_from' => now(),
            'effective_to' => null,
        ]);

        $this->assertSame('merchant_gateway', $rule->scope_type);
        $this->assertSame(1, $rule->scope_id);
        $this->assertSame('flat', $rule->fee_type);
        $this->assertSame('2.5000', (string) $rule->fee_value);
    }
}
