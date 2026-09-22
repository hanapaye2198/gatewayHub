<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

class MerchantPaymentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_report_includes_every_own_transaction_and_ignores_merchant_id_override(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $merchant->merchant?->forceFill(['name' => 'Davao Foundation'])->save();
        $this->createGateway('coins', 'Coins.ph');

        foreach (range(1, 26) as $index) {
            Payment::factory()->for($merchant->merchant)->create([
                'gateway_code' => 'coins',
                'reference_id' => 'OWN-REPORT-'.$index,
                'amount' => 10,
                'status' => $index % 2 === 0 ? 'paid' : 'pending',
                'raw_response' => ['client_secret' => 'report-gateway-secret'],
            ]);
        }

        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'OTHER-REPORT-LEAK',
            'status' => 'paid',
            'amount' => 9999,
            'platform_fee' => 149.99,
            'net_amount' => 9849.01,
        ]);

        $merchant->merchant?->forceFill([
            'api_key_hash' => 'report-api-key-hash',
            'webhook_secret' => 'report-webhook-secret',
        ])->save();

        $sample = Payment::query()->where('reference_id', 'OWN-REPORT-1')->firstOrFail();
        $updatedAt = $sample->updated_at?->toIso8601String();
        $status = $sample->status;

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.report', [
            'merchant_id' => $other->merchant_id,
        ]));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('payment_report_', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);

        $worksheet = $this->worksheet($response->streamedContent());

        foreach (range(1, 26) as $index) {
            $this->assertStringContainsString('OWN-REPORT-'.$index, $worksheet);
        }

        $this->assertStringContainsString('Davao Foundation', $worksheet);
        $this->assertStringNotContainsString('OTHER-REPORT-LEAK', $worksheet);
        $this->assertStringNotContainsString('9999.00', $worksheet);
        $this->assertStringNotContainsString('report-api-key-hash', $worksheet);
        $this->assertStringNotContainsString('report-webhook-secret', $worksheet);
        $this->assertStringNotContainsString('report-gateway-secret', $worksheet);
        $this->assertMatchesRegularExpression('/Total Transactions<\/t><\/is><\/c><c r="B3" t="n"><v>26<\/v><\/c>/', $worksheet);

        $sample->refresh();
        $this->assertSame($status, $sample->status);
        $this->assertSame($updatedAt, $sample->updated_at?->toIso8601String());
    }

    public function test_report_without_filters_includes_every_status_for_the_merchant_only(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'ALL-PAID',
            'status' => 'paid',
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'ALL-PENDING',
            'status' => 'pending',
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'ALL-OTHER',
            'status' => 'paid',
        ]);

        $worksheet = $this->worksheet(
            $this->actingAs($merchant)->get(route('dashboard.payments.report'))->assertOk()->streamedContent()
        );

        $this->assertStringContainsString('ALL-PAID', $worksheet);
        $this->assertStringContainsString('ALL-PENDING', $worksheet);
        $this->assertStringNotContainsString('ALL-OTHER', $worksheet);
        $this->assertMatchesRegularExpression('/Total Transactions<\/t><\/is><\/c><c r="B3" t="n"><v>2<\/v><\/c>/', $worksheet);
    }

    public function test_status_filter_limits_the_report_to_the_merchants_matching_payments(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'STATUS-PAID',
            'status' => 'paid',
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'STATUS-PENDING',
            'status' => 'pending',
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'STATUS-OTHER-PAID',
            'status' => 'paid',
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'status' => 'paid',
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertStringContainsString('STATUS-PAID', $worksheet);
        $this->assertStringNotContainsString('STATUS-PENDING', $worksheet);
        $this->assertStringNotContainsString('STATUS-OTHER-PAID', $worksheet);
    }

    public function test_gateway_filter_limits_the_report_to_the_merchants_matching_payments(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');
        $this->createGateway('gcash', 'GCash');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'GATEWAY-COINS',
            'status' => 'paid',
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'gcash',
            'reference_id' => 'GATEWAY-GCASH',
            'status' => 'paid',
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'GATEWAY-OTHER-COINS',
            'status' => 'paid',
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'gateway_code' => 'coins',
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertStringContainsString('GATEWAY-COINS', $worksheet);
        $this->assertStringContainsString('Coins.ph', $worksheet);
        $this->assertStringNotContainsString('GATEWAY-GCASH', $worksheet);
        $this->assertStringNotContainsString('GATEWAY-OTHER-COINS', $worksheet);
    }

    public function test_date_range_limits_the_report_to_the_merchants_matching_payments(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'DATE-IN-RANGE',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'DATE-TOO-OLD',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-08-01 08:00:00'),
            'updated_at' => Carbon::parse('2026-08-01 08:00:00'),
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'DATE-OTHER-IN-RANGE',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-22',
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertStringContainsString('DATE-IN-RANGE', $worksheet);
        $this->assertStringNotContainsString('DATE-TOO-OLD', $worksheet);
        $this->assertStringNotContainsString('DATE-OTHER-IN-RANGE', $worksheet);
    }

    public function test_reference_filter_limits_the_report_to_the_merchants_matching_payments(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'SEARCH-NEEDLE-1',
            'status' => 'paid',
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'SEARCH-OTHER',
            'status' => 'paid',
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'SEARCH-NEEDLE-LEAK',
            'status' => 'paid',
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'reference' => 'NEEDLE',
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertStringContainsString('SEARCH-NEEDLE-1', $worksheet);
        $this->assertStringNotContainsString('SEARCH-OTHER', $worksheet);
        $this->assertStringNotContainsString('SEARCH-NEEDLE-LEAK', $worksheet);
    }

    public function test_combined_filters_return_only_the_intersection_for_the_authenticated_merchant(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');
        $this->createGateway('gcash', 'GCash');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'MULTI-HIT',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'MULTI-PENDING',
            'status' => 'pending',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'gcash',
            'reference_id' => 'MULTI-GCASH',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'MULTI-OLD',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-08-01 08:00:00'),
            'updated_at' => Carbon::parse('2026-08-01 08:00:00'),
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'MULTI-OTHER',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'status' => 'paid',
            'gateway_code' => 'coins',
            'reference' => 'MULTI-HIT',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-22',
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertStringContainsString('MULTI-HIT', $worksheet);
        $this->assertStringNotContainsString('MULTI-PENDING', $worksheet);
        $this->assertStringNotContainsString('MULTI-GCASH', $worksheet);
        $this->assertStringNotContainsString('MULTI-OLD', $worksheet);
        $this->assertStringNotContainsString('MULTI-OTHER', $worksheet);
        $this->assertMatchesRegularExpression('/Total Transactions<\/t><\/is><\/c><c r="B3" t="n"><v>1<\/v><\/c>/', $worksheet);
    }

    public function test_report_totals_read_stored_fee_amounts_without_including_another_merchant(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-1000',
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 15,
            'net_amount' => 985,
            'paid_at' => Carbon::parse('2026-09-10 09:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-500',
            'amount' => 500,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 7.50,
            'net_amount' => 492.50,
            'paid_at' => Carbon::parse('2026-09-11 09:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-PENDING',
            'amount' => 50,
            'status' => 'pending',
            'platform_fee' => null,
            'net_amount' => null,
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-FAILED',
            'amount' => 20,
            'status' => 'failed',
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-REFUNDED',
            'amount' => 30,
            'status' => 'refunded',
        ]);
        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'TOTAL-OTHER',
            'amount' => 8000,
            'status' => 'paid',
            'platform_fee' => 120,
            'net_amount' => 7880,
        ]);

        $worksheet = $this->reportWorksheet($merchant, [
            'merchant_id' => $other->merchant_id,
        ]);

        $this->assertMatchesRegularExpression('/Total Transactions<\/t><\/is><\/c><c r="B3" t="n"><v>5<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Paid Transactions<\/t><\/is><\/c><c r="B4" t="n"><v>2<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Pending Transactions<\/t><\/is><\/c><c r="B5" t="n"><v>1<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Failed Transactions<\/t><\/is><\/c><c r="B6" t="n"><v>1<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Refunded Transactions<\/t><\/is><\/c><c r="B7" t="n"><v>1<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Gross Transaction Volume<\/t><\/is><\/c><c r="B8" t="n"><v>1600.00<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Platform Fees<\/t><\/is><\/c><c r="B9" t="n"><v>22.50<\/v><\/c>/', $worksheet);
        $this->assertMatchesRegularExpression('/Merchant Net Amount<\/t><\/is><\/c><c r="B10" t="n"><v>1477.50<\/v><\/c>/', $worksheet);
        $this->assertStringContainsString('15.00', $worksheet);
        $this->assertStringContainsString('7.50', $worksheet);
        $this->assertStringContainsString('985.00', $worksheet);
        $this->assertStringContainsString('492.50', $worksheet);
        $this->assertStringNotContainsString('TOTAL-OTHER', $worksheet);
        $this->assertStringNotContainsString('8000.00', $worksheet);
    }

    public function test_guests_and_platform_admins_cannot_download_the_merchant_payment_report(): void
    {
        $this->get(route('dashboard.payments.report'))->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.payments.report'))
            ->assertRedirect(route('admin.index'));
    }

    public function test_payments_page_links_to_the_payment_report_without_a_merchant_id(): void
    {
        $merchant = User::factory()->create();

        $this->actingAs($merchant)
            ->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('Download Payment Report')
            ->assertSee(route('dashboard.payments.report'), false)
            ->assertDontSee('merchant_id=');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportWorksheet(User $merchant, array $filters): string
    {
        return $this->worksheet(
            $this->actingAs($merchant)->get(route('dashboard.payments.report', $filters))->assertOk()->streamedContent()
        );
    }

    private function createGateway(string $code, string $name): void
    {
        Gateway::query()->create([
            'code' => $code,
            'name' => $name,
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);
    }

    private function worksheet(string $workbook): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-report-test-');
        $this->assertNotFalse($temporaryPath);

        try {
            $this->assertNotFalse(file_put_contents($temporaryPath, $workbook));

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($temporaryPath) === true);
            $contents = $archive->getFromName('xl/worksheets/sheet1.xml');
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
