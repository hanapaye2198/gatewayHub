<?php

namespace Tests\Unit\Bootstrap;

use App\Bootstrap\ValidateProductionEnvironment;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;
use Tests\TestCase;

class ValidateProductionEnvironmentTest extends TestCase
{
    public function test_it_skips_validation_outside_production(): void
    {
        config()->set('app.debug', true);

        $validator = new ValidateProductionEnvironment;

        $validator->bootstrap($this->applicationMock(false));

        $this->assertTrue(true);
    }

    public function test_it_allows_valid_production_configuration(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.debug', false);
        config()->set('coins.gateway.client_id', 'coins-client-id');
        config()->set('coins.gateway.client_secret', 'coins-client-secret');
        config()->set('coins.webhook.secret', 'coins-webhook-secret');
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('gcash.webhook.allow_dev_bypass', false);
        config()->set('maya.webhook.allow_dev_bypass', false);
        config()->set('paypal.webhook.allow_dev_bypass', false);
        config()->set('paypal.webhook.client_id', '');
        config()->set('paypal.webhook.client_secret', '');
        config()->set('paypal.webhook.webhook_id', '');

        $validator = new ValidateProductionEnvironment;

        $validator->bootstrap($this->applicationMock(true));

        $this->assertTrue(true);
    }

    public function test_it_fails_when_production_security_flags_are_not_safe(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.debug', true);
        config()->set('coins.gateway.client_id', 'coins-client-id');
        config()->set('coins.gateway.client_secret', 'coins-client-secret');
        config()->set('coins.webhook.secret', 'coins-webhook-secret');
        config()->set('coins.webhook.allow_dev_bypass', true);
        config()->set('gcash.webhook.allow_dev_bypass', false);
        config()->set('maya.webhook.allow_dev_bypass', false);
        config()->set('paypal.webhook.allow_dev_bypass', false);

        $validator = new ValidateProductionEnvironment;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false in production.');

        $validator->bootstrap($this->applicationMock(true));
    }

    public function test_it_fails_when_coins_credentials_are_missing_in_production(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.debug', false);
        config()->set('coins.gateway.client_id', '');
        config()->set('coins.gateway.client_secret', '');
        config()->set('coins.webhook.secret', '');
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('gcash.webhook.allow_dev_bypass', false);
        config()->set('maya.webhook.allow_dev_bypass', false);
        config()->set('paypal.webhook.allow_dev_bypass', false);
        config()->set('paypal.webhook.client_id', '');
        config()->set('paypal.webhook.client_secret', '');
        config()->set('paypal.webhook.webhook_id', '');

        $validator = new ValidateProductionEnvironment;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Coins gateway credentials must be set');

        $validator->bootstrap($this->applicationMock(true));
    }

    private function applicationMock(bool $isProduction): Application
    {
        $app = $this->createMock(Application::class);
        $app->method('environment')->with('production')->willReturn($isProduction);

        return $app;
    }
}
