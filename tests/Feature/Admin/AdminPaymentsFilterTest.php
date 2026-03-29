<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminPaymentsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_payments_by_merchant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchantA = User::factory()->create(['name' => 'Merchant Alpha']);
        $merchantB = User::factory()->create(['name' => 'Merchant Beta']);

        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'reference_id' => 'FILTER-MERCHANT-A',
            'gateway_code' => 'coins',
            'status' => 'paid',
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchantB->id,
            'reference_id' => 'FILTER-MERCHANT-B',
            'gateway_code' => 'coins',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', [
            'merchant_id' => $merchantA->id,
        ]));

        $response->assertOk();
        $response->assertSee('FILTER-MERCHANT-A');
        $response->assertDontSee('FILTER-MERCHANT-B');
    }

    public function test_admin_can_filter_payments_by_gateway_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create();

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => 'App\Services\Gateways\Drivers\MayaDriver',
            'is_global_enabled' => true,
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'FILTER-COINS-PENDING',
            'gateway_code' => 'coins',
            'status' => 'pending',
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'FILTER-COINS-PAID',
            'gateway_code' => 'coins',
            'status' => 'paid',
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'FILTER-MAYA-PAID',
            'gateway_code' => 'maya',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', [
            'gateway_code' => 'coins',
            'status' => 'paid',
        ]));

        $response->assertOk();
        $response->assertSee('FILTER-COINS-PAID');
        $response->assertDontSee('FILTER-COINS-PENDING');
        $response->assertDontSee('FILTER-MAYA-PAID');
    }

    public function test_admin_can_filter_payments_by_date_range_and_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'DATE-RANGE-MATCH-001',
            'provider_reference' => 'PREF-MATCH-001',
            'gateway_code' => 'coins',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-02-12 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-12 10:00:00'),
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'DATE-RANGE-OLD-002',
            'provider_reference' => 'PREF-OLD-002',
            'gateway_code' => 'coins',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-01-20 10:00:00'),
            'updated_at' => Carbon::parse('2026-01-20 10:00:00'),
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'DATE-RANGE-WRONG-003',
            'provider_reference' => 'PREF-WRONG-003',
            'gateway_code' => 'coins',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-02-12 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-12 10:00:00'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', [
            'reference' => 'MATCH',
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertSee('DATE-RANGE-MATCH-001');
        $response->assertDontSee('DATE-RANGE-OLD-002');
        $response->assertDontSee('DATE-RANGE-WRONG-003');
    }

    public function test_admin_payments_page_summary_reflects_active_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchantA = User::factory()->create();
        $merchantB = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'gateway_code' => 'coins',
            'status' => 'paid',
            'amount' => 100,
            'reference_id' => 'SUMMARY-PAID-A',
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'gateway_code' => 'coins',
            'status' => 'pending',
            'amount' => 50,
            'reference_id' => 'SUMMARY-PENDING-A',
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'gateway_code' => 'coins',
            'status' => 'failed',
            'amount' => 70,
            'reference_id' => 'SUMMARY-FAILED-A',
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchantB->id,
            'gateway_code' => 'coins',
            'status' => 'paid',
            'amount' => 999,
            'reference_id' => 'SUMMARY-PAID-B',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', [
            'merchant_id' => $merchantA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_transactions'] === 3
                && (float) $summary['paid_collections'] === 100.0
                && $summary['pending_count'] === 1
                && $summary['failed_refunded_count'] === 1;
        });
    }

    public function test_admin_can_export_filtered_payments_to_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create(['name' => 'CSV Merchant']);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'CSV-PAID-001',
            'provider_reference' => 'CSV-PROVIDER-001',
            'gateway_code' => 'coins',
            'status' => 'paid',
            'amount' => 321.25,
            'created_at' => Carbon::parse('2026-02-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-02-10 09:00:00'),
        ]);

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'reference_id' => 'CSV-PENDING-002',
            'provider_reference' => 'CSV-PROVIDER-002',
            'gateway_code' => 'coins',
            'status' => 'pending',
            'amount' => 150,
            'created_at' => Carbon::parse('2026-02-11 09:00:00'),
            'updated_at' => Carbon::parse('2026-02-11 09:00:00'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.export', [
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'reference' => 'CSV-',
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('admin-payments-', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $rows = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($csv)) ?: [],
            static fn (string $line): bool => $line !== ''
        ));

        $this->assertCount(2, $rows);

        $header = str_getcsv($rows[0]);
        $this->assertSame([
            'Created At',
            'Reference',
            'Provider Reference',
            'Merchant',
            'Gateway',
            'Amount',
            'Currency',
            'Platform Fee',
            'Net Amount',
            'Status',
        ], $header);

        $data = str_getcsv($rows[1]);
        $this->assertSame('2026-02-10 09:00:00', $data[0] ?? null);
        $this->assertSame('CSV-PAID-001', $data[1] ?? null);
        $this->assertSame('CSV Merchant', $data[3] ?? null);
        $this->assertSame('321.25', $data[5] ?? null);
        $this->assertSame('paid', $data[9] ?? null);

        $this->assertStringNotContainsString('CSV-PENDING-002', $csv);
    }
}
