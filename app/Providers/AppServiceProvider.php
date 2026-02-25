<?php

namespace App\Providers;

use App\Bootstrap\ValidateProductionEnvironment;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        (new ValidateProductionEnvironment)->bootstrap($this->app);
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->registerRequestMacros();
        Payment::observe(PaymentObserver::class);
    }

    /**
     * Configure rate limiters for API and webhook endpoints.
     */
    protected function configureRateLimiting(): void
    {
        $apiMax = config('rate-limiting.api.max_attempts', 60);
        $webhooksMax = config('rate-limiting.webhooks.max_attempts', 200);

        RateLimiter::for('api', function (Request $request) use ($apiMax) {
            $key = $request->merchant()?->id ?? $request->ip();

            return Limit::perMinute($apiMax)
                ->by((string) $key)
                ->response(function (Request $req, array $headers) use ($apiMax) {
                    Log::warning('API rate limit exceeded', [
                        'limiter' => 'api',
                        'limit' => $apiMax,
                        'key_type' => $req->merchant() !== null ? 'merchant' : 'ip',
                    ]);

                    return ApiResponse::error('Too many requests. Please try again later.', 429)
                        ->withHeaders($headers);
                });
        });

        RateLimiter::for('webhooks', function (Request $request) use ($webhooksMax) {
            return Limit::perMinute($webhooksMax)
                ->by($request->ip())
                ->response(function (Request $req, array $headers) use ($webhooksMax) {
                    Log::warning('Webhook rate limit exceeded', [
                        'limiter' => 'webhooks',
                        'limit' => $webhooksMax,
                        'ip' => $req->ip(),
                    ]);

                    return ApiResponse::error('Too many webhook requests. Please try again later.', 429)
                        ->withHeaders($headers);
                });
        });
    }

    protected function registerRequestMacros(): void
    {
        Request::macro('merchant', function (): ?\App\Models\User {
            return $this->attributes->get('merchant');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
