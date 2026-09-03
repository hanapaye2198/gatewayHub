<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

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
        $response->assertSee('Download Excel');
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

    public function test_admin_can_download_selected_merchant_payments_as_excel(): void
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
            'platform_fee' => 4.82,
            'net_amount' => 316.43,
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
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $worksheet = $this->readZipEntry($response->streamedContent(), 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('CSV-PAID-001', $worksheet);
        $this->assertStringContainsString('321.25', $worksheet);
        $this->assertStringContainsString('4.82', $worksheet);
        $this->assertStringContainsString('316.43', $worksheet);
        $this->assertStringNotContainsString('CSV-PENDING-002', $worksheet);
    }

    public function test_admin_can_download_all_merchants_as_separate_excel_workbooks_in_a_zip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchantA = User::factory()->create(['name' => 'Merchant Alpha']);
        $merchantB = User::factory()->create(['name' => 'Merchant Beta']);

        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'reference_id' => 'ALL-MERCHANTS-ALPHA',
            'status' => 'paid',
            'amount' => 100,
            'platform_fee' => 1.50,
            'net_amount' => 98.50,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchantB->id,
            'reference_id' => 'ALL-MERCHANTS-BETA',
            'status' => 'paid',
            'amount' => 200,
            'platform_fee' => 3.00,
            'net_amount' => 197.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.export'));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));

        $archive = $response->streamedContent();
        $alphaPath = 'merchants/merchant-alpha-'.$merchantA->id.'/transactions.xlsx';
        $betaPath = 'merchants/merchant-beta-'.$merchantB->id.'/transactions.xlsx';

        $alphaWorkbook = $this->readZipEntry($archive, $alphaPath);
        $betaWorkbook = $this->readZipEntry($archive, $betaPath);
        $alphaWorksheet = $this->readZipEntry($alphaWorkbook, 'xl/worksheets/sheet1.xml');
        $betaWorksheet = $this->readZipEntry($betaWorkbook, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('ALL-MERCHANTS-ALPHA', $alphaWorksheet);
        $this->assertStringNotContainsString('ALL-MERCHANTS-BETA', $alphaWorksheet);
        $this->assertStringContainsString('ALL-MERCHANTS-BETA', $betaWorksheet);
        $this->assertStringNotContainsString('ALL-MERCHANTS-ALPHA', $betaWorksheet);
    }

    private function readZipEntry(string $archiveContent, string $entry): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-test-archive-');
        $this->assertNotFalse($temporaryPath);

        try {
            $this->assertNotFalse(file_put_contents($temporaryPath, $archiveContent));

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($temporaryPath) === true);
            $contents = $archive->getFromName($entry);
            $archive->close();

            $this->assertIsString($contents);

            return $contents;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
