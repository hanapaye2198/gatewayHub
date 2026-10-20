<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\Fortify\PostLoginRedirect;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KemoFiController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        $requestId = $request->query('request_id');
        if ($requestId !== null && (! is_string($requestId) || preg_match('/^[A-Za-z0-9_-]{32,128}$/', $requestId) !== 1)) {
            abort(400, __('The KemoFi connection request is invalid.'));
        }

        $state = Str::random(48);
        $pendingStates = $request->session()->get('kemofi_sso_states', []);
        $pendingStates = is_array($pendingStates)
            ? array_filter($pendingStates, static fn (mixed $expiresAt): bool => is_int($expiresAt) && $expiresAt >= time())
            : [];
        $pendingStates[$state] = time() + 600;
        $request->session()->put('kemofi_sso_states', $pendingStates);

        $query = ['state' => $state];
        if (is_string($requestId) && $requestId !== '') {
            $query['request_id'] = $requestId;
        }

        $url = rtrim((string) config('kemofi_sso.base_url'), '/').'/kemopay-sso?'.http_build_query($query);

        $response = redirect()->away($url);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureConfigured();
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        if (preg_match('/^[A-Za-z0-9_-]{32,128}$/', $code) !== 1 ||
            preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) !== 1) {
            abort(400, __('The KemoFi authorization is invalid.'));
        }
        $pendingStates = $request->session()->get('kemofi_sso_states', []);
        $stateExpiresAt = is_array($pendingStates) ? ($pendingStates[$state] ?? null) : null;
        if (is_array($pendingStates)) {
            unset($pendingStates[$state]);
            $request->session()->put('kemofi_sso_states', $pendingStates);
        }
        if (! is_int($stateExpiresAt) || $stateExpiresAt < time()) {
            abort(403, __('This KemoFi sign-in request has expired. Please start again.'));
        }

        $validated = ['code' => $code, 'state' => $state];

        try {
            $exchange = $this->postToKemoFi('/api/kemopay/sso/exchange', $validated);
        } catch (ConnectionException) {
            abort(503, __('KemoFi sign-in is temporarily unavailable. Please try again.'));
        }

        if (! $exchange->successful() || ! is_array($exchange->json('identity'))) {
            abort(401, __('KemoFi could not verify this sign-in. Please start again.'));
        }

        $identity = $exchange->json('identity');
        $subject = trim((string) ($identity['subject'] ?? ''));
        $email = mb_strtolower(trim((string) ($identity['email'] ?? '')));
        $name = trim((string) ($identity['name'] ?? ''));
        $purpose = (string) ($identity['purpose'] ?? '');
        $providerName = trim((string) ($identity['provider_name'] ?? ''));

        if (preg_match('/^(reseller|customer):[0-9]{1,20}$/', $subject) !== 1 ||
            filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
            ! in_array($purpose, ['login', 'connect'], true)) {
            abort(401, __('KemoFi returned an invalid account identity.'));
        }

        $user = DB::transaction(function () use ($subject, $email, $name, $purpose, $providerName): User {
            $user = User::query()->where('kemofi_subject', $subject)->first();

            if ($user === null) {
                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
                if ($user !== null && (
                    $user->role !== User::ROLE_MERCHANT_USER ||
                    $user->email_verified_at === null ||
                    $user->kemofi_subject !== null ||
                    ! $user->is_active ||
                    $user->two_factor_confirmed_at !== null
                )) {
                    throw new HttpException(409, __('This email belongs to a KemoPay account that cannot be linked automatically. Sign in to that account and contact support to connect it.'));
                }

                if ($user === null) {
                    $user = User::query()->create([
                        'name' => $name !== '' ? mb_substr($name, 0, 255) : $email,
                        'email' => $email,
                        'password' => Str::random(64),
                        'role' => User::ROLE_MERCHANT_USER,
                        'is_active' => true,
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $user->forceFill(['kemofi_subject' => $subject])->save();
            }

            if ($user->role !== User::ROLE_MERCHANT_USER || ! $user->is_active || $user->email_verified_at === null) {
                throw new HttpException(403, __('This KemoPay account cannot use KemoFi sign-in.'));
            }

            if ($user->two_factor_confirmed_at !== null) {
                throw new HttpException(403, __('This KemoPay account has two-factor sign-in enabled. Sign in directly to KemoPay to preserve that protection.'));
            }

            $merchant = $user->merchant;
            if ($merchant === null) {
                $merchant = Merchant::provisionForUser($user);
                if ($purpose === 'connect' && $providerName !== '') {
                    $merchant->forceFill(['name' => mb_substr($providerName, 0, 255)])->save();
                }
            }

            if (! $merchant->is_active) {
                throw new HttpException(403, __('This KemoPay merchant account is inactive.'));
            }

            // Older merchants may have only the one-way API key hash. Rotate
            // that key here so the authorized KemoFi provider can receive the
            // encrypted server-side copy required for PPPoE payment routing.
            if (! $merchant->hasApiKey() || trim((string) $merchant->api_key_encrypted) === '') {
                $merchant->regenerateApiKey();
                $merchant->refresh();
            }

            if ($purpose === 'connect') {
                $apiKey = trim((string) $merchant->api_key_encrypted);
                if ($apiKey === '') {
                    throw new HttpException(409, __('Regenerate the KemoPay merchant API key before connecting this account.'));
                }

                $webhookSecret = trim((string) $merchant->webhook_secret);
                if ($webhookSecret === '') {
                    $webhookSecret = Str::random(48);
                    $merchant->forceFill(['webhook_secret' => $webhookSecret])->save();
                }
            }

            return $user->refresh();
        });

        $merchant = $user->merchant;
        if ($merchant === null) {
            abort(409, __('The KemoPay merchant account is incomplete.'));
        }

        if ($purpose === 'connect') {
            $apiKey = trim((string) $merchant->api_key_encrypted);
            $webhookSecret = trim((string) $merchant->webhook_secret);
            $completion = [
                'code' => $validated['code'],
                'state' => $validated['state'],
                'merchant_id' => (string) $merchant->id,
                'api_key' => $apiKey,
                'webhook_secret' => $webhookSecret,
            ];
        } else {
            $completion = [
                'code' => $validated['code'],
                'state' => $validated['state'],
            ];
        }

        try {
            $completed = $this->postToKemoFi('/api/kemopay/sso/complete', $completion);
        } catch (ConnectionException) {
            abort(503, __('KemoFi could not finish the account link. Please retry sign-in.'));
        }

        if (! $completed->successful() || $completed->json('completed') !== true) {
            Log::warning('KemoFi SSO completion failed', ['http_status' => $completed->status()]);
            abort(502, __('KemoFi could not finish the account link. Please retry sign-in.'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        $response = redirect()->to(PostLoginRedirect::path($user));
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function ensureConfigured(): void
    {
        $baseUrl = rtrim((string) config('kemofi_sso.base_url'), '/');
        $secret = (string) config('kemofi_sso.shared_secret');

        if (preg_match('/^[A-Za-z0-9_-]{32,512}$/', $secret) !== 1 || ! str_starts_with($baseUrl, 'https://')) {
            abort(503, __('KemoFi sign-in is not configured on this KemoPay server.'));
        }
    }

    private function postToKemoFi(string $path, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['X-KemoPay-SSO-Secret' => (string) config('kemofi_sso.shared_secret')])
            ->timeout(max(1, min(30, (int) config('kemofi_sso.timeout_seconds', 10))))
            ->post(rtrim((string) config('kemofi_sso.base_url'), '/').$path, $payload);
    }
}
