<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

class MerchantPaymentsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_download_every_own_transaction_and_not_another_merchants(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');

        foreach (range(1, 26) as $index) {
            Payment::factory()->for($merchant->merchant)->create([
                'gateway_code' => 'coins',
                'reference_id' => 'OWN-EXPORT-'.$index,
                'status' => $index % 2 === 0 ? 'paid' : 'pending',
                'raw_response' => ['client_secret' => 'gateway-export-secret'],
            ]);
        }

        Payment::factory()->for($other->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'OTHER-EXPORT-LEAK',
            'status' => 'paid',
        ]);

        $merchant->merchant?->forceFill([
            'api_key_hash' => 'export-api-key-hash',
            'webhook_secret' => 'export-webhook-secret',
        ])->save();

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.export', [
            'merchant_id' => $other->merchant_id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('transactions_', (string) $response->headers->get('content-disposition'));

        $worksheet = $this->worksheet($response->streamedContent());

        foreach (range(1, 26) as $index) {
            $this->assertStringContainsString('OWN-EXPORT-'.$index, $worksheet);
        }

        $this->assertStringNotContainsString('OTHER-EXPORT-LEAK', $worksheet);
        $this->assertStringNotContainsString('export-api-key-hash', $worksheet);
        $this->assertStringNotContainsString('export-webhook-secret', $worksheet);
        $this->assertStringNotContainsString('gateway-export-secret', $worksheet);
    }

    public function test_merchant_id_in_the_path_is_not_an_export_route(): void
    {
        $merchant = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($merchant)
            ->get('/dashboard/payments/export/'.$other->merchant_id)
            ->assertNotFound();
    }

    public function test_status_gateway_and_date_filters_limit_the_merchant_export(): void
    {
        $merchant = User::factory()->create();
        $this->createGateway('coins', 'Coins.ph');
        $this->createGateway('gcash', 'GCash');

        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'FILTER-PAID-COINS',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'gcash',
            'reference_id' => 'FILTER-PAID-GCASH',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'FILTER-PENDING-COINS',
            'status' => 'pending',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        Payment::factory()->for($merchant->merchant)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'FILTER-OLD-COINS',
            'status' => 'paid',
            'created_at' => Carbon::parse('2026-08-01 08:00:00'),
            'updated_at' => Carbon::parse('2026-08-01 08:00:00'),
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.payments.export', [
            'status' => 'paid',
            'gateway_code' => 'coins',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-22',
            'merchant_id' => 999999,
        ]));

        $worksheet = $this->worksheet($response->assertOk()->streamedContent());

        $this->assertStringContainsString('FILTER-PAID-COINS', $worksheet);
        $this->assertStringNotContainsString('FILTER-PAID-GCASH', $worksheet);
        $this->assertStringNotContainsString('FILTER-PENDING-COINS', $worksheet);
        $this->assertStringNotContainsString('FILTER-OLD-COINS', $worksheet);
    }

    public function test_guests_and_platform_admins_cannot_use_the_merchant_export(): void
    {
        $this->get(route('dashboard.payments.export'))->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.payments.export'))
            ->assertRedirect(route('admin.index'));
    }

    public function test_payments_page_links_to_the_merchant_export_without_a_merchant_id(): void
    {
        $merchant = User::factory()->create();

        $this->actingAs($merchant)
            ->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('Download Transactions')
            ->assertSee(route('dashboard.payments.export'), false)
            ->assertDontSee('merchant_id=');
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
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-export-test-');
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
