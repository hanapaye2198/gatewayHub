<?php

namespace App\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

class ValidateProductionEnvironment
{
    /**
     * Validate critical environment variables in production. Fails fast if missing.
     */
    public function bootstrap(Application $app): void
    {
        if (! $app->environment('production')) {
            return;
        }

        $errors = [];

        $env = config('app.env');
        if (empty($env) || ! is_string($env)) {
            $errors[] = 'APP_ENV must be set.';
        }

        $key = config('app.key');
        if (empty($key) || $key === 'base64:' || ! str_starts_with((string) $key, 'base64:')) {
            $errors[] = 'APP_KEY must be set. Run: php artisan key:generate';
        }

        $this->validateDatabase($errors);
        $this->validatePayPal($errors);

        if ($errors !== []) {
            throw new RuntimeException(
                'Production environment validation failed: '.implode(' ', $errors)
            );
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateDatabase(array &$errors): void
    {
        $connection = config('database.default');
        if (empty($connection)) {
            $errors[] = 'DB_CONNECTION must be set.';

            return;
        }

        if ($connection === 'sqlite') {
            $database = config('database.connections.sqlite.database');
            if (empty($database)) {
                $errors[] = 'DB_DATABASE or database path must be set for SQLite.';
            }

            return;
        }

        $required = ['host' => 'DB_HOST', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME'];
        foreach ($required as $key => $envKey) {
            $value = config("database.connections.{$connection}.{$key}");
            if ($value === null || $value === '') {
                $errors[] = "{$envKey} must be set for database connection.";
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validatePayPal(array &$errors): void
    {
        $clientId = config('paypal.webhook.client_id', '');
        $clientSecret = config('paypal.webhook.client_secret', '');
        $webhookId = config('paypal.webhook.webhook_id', '');
        $clientId = is_string($clientId) ? trim($clientId) : '';
        $clientSecret = is_string($clientSecret) ? trim($clientSecret) : '';
        $webhookId = is_string($webhookId) ? trim($webhookId) : '';

        $anySet = $clientId !== '' || $clientSecret !== '' || $webhookId !== '';
        $allSet = $clientId !== '' && $clientSecret !== '' && $webhookId !== '';

        if ($anySet && ! $allSet) {
            $errors[] = 'PayPal: when using PayPal webhooks, PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, and PAYPAL_WEBHOOK_ID must all be set.';
        }
    }
}
