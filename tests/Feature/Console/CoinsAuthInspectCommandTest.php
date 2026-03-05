<?php

namespace Tests\Feature\Console;

use App\Models\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinsAuthInspectCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_coins_auth_inspect_reports_env_source_when_no_db_override_exists(): void
    {
        config([
            'coins.gateway.client_id' => 'env-client-id',
            'coins.gateway.client_secret' => 'env-client-secret',
            'coins.webhook.secret' => 'env-webhook-secret',
            'coins.auth.generate_qr.strategy' => 'auto',
            'coins.auth.generate_qr.timestamp_unit' => 'milliseconds',
            'coins.auth.generate_qr.signature_encoding' => 'hex_lower',
            'coins.auth.generate_qr.max_attempts' => 4,
        ]);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [],
        ]);

        $this->artisan('coins:auth:inspect')
            ->expectsOutputToContain('credential_source          env')
            ->expectsOutputToContain('strategy                   auto')
            ->expectsOutputToContain('timestamp_unit             milliseconds')
            ->expectsOutputToContain('client_id                  SET')
            ->expectsOutputToContain('client_secret              SET')
            ->expectsOutputToContain('webhook_secret             SET')
            ->assertExitCode(0);
    }

    public function test_coins_auth_inspect_reports_db_source_and_does_not_print_secrets(): void
    {
        config([
            'coins.gateway.client_id' => 'env-client-id',
            'coins.gateway.client_secret' => 'env-client-secret',
            'coins.webhook.secret' => 'env-webhook-secret',
        ]);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'db-client-id',
                'client_secret' => 'db-client-secret',
                'api_base' => 'prod',
                'webhook_secret' => 'db-webhook-secret',
            ],
        ]);

        $this->artisan('coins:auth:inspect')
            ->expectsOutputToContain('credential_source          db')
            ->expectsOutputToContain('api_base                   prod')
            ->expectsOutputToContain('client_id                  SET')
            ->expectsOutputToContain('client_secret              SET')
            ->expectsOutputToContain('webhook_secret             SET')
            ->doesntExpectOutputToContain('db-client-secret')
            ->doesntExpectOutputToContain('db-webhook-secret')
            ->assertExitCode(0);
    }
}
