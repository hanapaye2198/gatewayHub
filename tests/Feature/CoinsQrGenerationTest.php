<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinsQrGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Http::fake();
    }

    public function test_legacy_coins_qr_page_is_no_longer_available(): void
    {
        $this->actingAs($this->user)->get('/coins/qr')->assertNotFound();
    }

    public function test_legacy_coins_qr_api_is_unavailable_without_authentication(): void
    {
        $this->postJson('/coins/generate-qr', ['amount' => 100])->assertNotFound();
    }

    public function test_legacy_coins_qr_api_does_not_validate_amounts_or_start_provider_requests(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable([]);
    }

    public function test_legacy_coins_qr_api_does_not_accept_below_minimum_amounts(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 0.5]);
    }

    public function test_legacy_coins_qr_api_does_not_create_a_transaction(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 100]);
    }

    public function test_legacy_coins_qr_api_does_not_call_the_provider_on_error_responses(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 100]);
    }

    public function test_legacy_coins_qr_api_does_not_expose_provider_feature_errors(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 100]);
    }

    public function test_legacy_coins_qr_api_does_not_retry_signature_strategies(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 50]);
    }

    public function test_legacy_coins_qr_api_does_not_retry_raw_json_signatures(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 50]);
    }

    public function test_legacy_coins_qr_api_does_not_use_a_timestamp_unit(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 50]);
    }

    public function test_legacy_coins_qr_api_does_not_return_provider_error_keys(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 50]);
    }

    public function test_legacy_coins_qr_api_does_not_use_shared_provider_credentials(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 50]);
    }

    public function test_legacy_coins_qr_api_does_not_parse_plain_text_provider_errors(): void
    {
        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 25]);
    }

    public function test_legacy_coins_qr_api_does_not_fallback_to_legacy_credentials(): void
    {
        config(['coins.api_key' => '', 'coins.secret_key' => '']);

        $this->assertLegacyCoinsQrApiIsUnavailable(['amount' => 10]);
    }

    /**
     * @param  array<string, int|float|string>  $payload
     */
    private function assertLegacyCoinsQrApiIsUnavailable(array $payload): void
    {
        $this->actingAs($this->user)->postJson('/coins/generate-qr', $payload)->assertNotFound();

        Http::assertNothingSent();
        $this->assertDatabaseCount('coins_transactions', 0);
    }
}
