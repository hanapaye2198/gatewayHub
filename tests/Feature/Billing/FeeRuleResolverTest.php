<?php

namespace Tests\Feature\Billing;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\PlatformFeeRule;
use App\Models\User;
use App\Services\Billing\FeeRuleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    private FeeRuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FeeRuleResolver;
    }

    public function test_resolves_global_rule_when_no_more_specific_rule(): void
    {
        $paymentDate = CarbonImmutable::parse('2025-02-01 12:00:00');
        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.005,
            'is_active' => true,
            'effective_from' => $paymentDate->subDay(),
            'effective_to' => null,
        ]);

        $rule = $this->resolver->resolve(1, 'coins', $paymentDate);

        $this->assertNotNull($rule);
        $this->assertSame('global', $rule->scope_type);
        $this->assertSame('percentage', $rule->fee_type);
        $this->assertSame('0.0050', (string) $rule->fee_value);
    }

    public function test_resolution_order_merchant_gateway_beats_global(): void
    {
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins',
            'driver_class' => 'App\Drivers\Coins',
            'is_global_enabled' => true,
        ]);
        $user = User::factory()->create();
        $mg = MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $paymentDate = CarbonImmutable::now();

        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.01,
            'is_active' => true,
            'effective_from' => $paymentDate->subDay(),
            'effective_to' => null,
        ]);
        PlatformFeeRule::query()->create([
            'scope_type' => 'merchant_gateway',
            'scope_id' => $mg->id,
            'fee_type' => 'flat',
            'fee_value' => 2.5,
            'is_active' => true,
            'effective_from' => $paymentDate->subDay(),
            'effective_to' => null,
        ]);

        $rule = $this->resolver->resolve((int) $user->id, 'coins', $paymentDate);

        $this->assertNotNull($rule);
        $this->assertSame('merchant_gateway', $rule->scope_type);
        $this->assertSame('flat', $rule->fee_type);
        $this->assertSame('2.5000', (string) $rule->fee_value);
    }

    public function test_inactive_rule_skipped(): void
    {
        PlatformFeeRule::query()->delete();

        $paymentDate = CarbonImmutable::now();
        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.005,
            'is_active' => false,
            'effective_from' => $paymentDate->subDay(),
            'effective_to' => null,
        ]);

        $rule = $this->resolver->resolve(1, 'coins', $paymentDate);

        $this->assertNull($rule);
    }

    public function test_rule_outside_effective_to_skipped(): void
    {
        PlatformFeeRule::query()->delete();

        $paymentDate = CarbonImmutable::parse('2025-02-15 12:00:00');
        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.005,
            'is_active' => true,
            'effective_from' => $paymentDate->subDays(10),
            'effective_to' => $paymentDate->subDay(),
        ]);

        $rule = $this->resolver->resolve(1, 'coins', $paymentDate);

        $this->assertNull($rule);
    }

    public function test_rule_effective_on_boundary_date(): void
    {
        $paymentDate = CarbonImmutable::parse('2025-02-10 00:00:00');
        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.005,
            'is_active' => true,
            'effective_from' => $paymentDate,
            'effective_to' => $paymentDate,
        ]);

        $rule = $this->resolver->resolve(1, 'coins', $paymentDate);

        $this->assertNotNull($rule);
    }

    public function test_returns_null_when_no_rules(): void
    {
        PlatformFeeRule::query()->delete();

        $rule = $this->resolver->resolve(1, 'coins', CarbonImmutable::now());

        $this->assertNull($rule);
    }

    public function test_deterministic_same_inputs_same_result(): void
    {
        $paymentDate = CarbonImmutable::parse('2025-02-01 12:00:00');
        PlatformFeeRule::query()->create([
            'scope_type' => 'global',
            'scope_id' => null,
            'fee_type' => 'percentage',
            'fee_value' => 0.005,
            'is_active' => true,
            'effective_from' => $paymentDate->subDay(),
            'effective_to' => null,
        ]);

        $rule1 = $this->resolver->resolve(99, 'coins', $paymentDate);
        $rule2 = $this->resolver->resolve(99, 'coins', $paymentDate);

        $this->assertNotNull($rule1);
        $this->assertTrue($rule1->is($rule2));
    }
}
