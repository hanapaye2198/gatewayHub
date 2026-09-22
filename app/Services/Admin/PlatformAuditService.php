<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Append-only platform administration audit trail.
 * The actor is the authenticated user, never a request field.
 */
final class PlatformAuditService
{
    /**
     * Fragments matched against array keys. Values under these keys are dropped.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'api_secret',
        'credential',
        'recovery',
        'two_factor',
        'remember_token',
        'authorization',
        'cookie',
        'config_json',
        'private_key',
        'access_key',
        'client_secret',
    ];

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $action,
        ?Merchant $merchant,
        ?Model $target,
        array $oldValues,
        array $newValues,
        string $description,
    ): PlatformAuditLog {
        $old = $this->sanitize($oldValues);
        $new = $this->sanitize($newValues);
        $request = request();
        $userAgent = $request?->userAgent();

        return PlatformAuditLog::query()->create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'merchant_id' => $merchant?->id,
            'target_type' => $target instanceof Model ? class_basename($target) : null,
            'target_id' => $target instanceof Model ? (string) $target->getKey() : null,
            'description' => $description,
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip_address' => $request?->ip(),
            'user_agent' => is_string($userAgent) ? Str::limit($userAgent, 1000, '') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function merchantSnapshot(Merchant $merchant): array
    {
        return [
            'name' => $merchant->name,
            'email' => $merchant->email,
            'is_active' => (bool) $merchant->is_active,
            'theme_color' => $merchant->theme_color,
            'qr_display_name' => $merchant->qr_display_name,
            'webhook_url' => $merchant->webhook_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userSnapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'merchant_id' => $user->merchant_id === null ? null : (int) $user->merchant_id,
            'is_active' => (bool) $user->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function changes(array $before, array $after): array
    {
        $before = $this->sanitize($before);
        $after = $this->sanitize($after);
        $old = [];
        $new = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $left = $before[$key] ?? null;
            $right = $after[$key] ?? null;

            if ($this->sameValue($left, $right)) {
                continue;
            }

            $old[$key] = $left;
            $new[$key] = $right;
        }

        return [$old, $new];
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public function sanitize(array $values, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $clean = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitize($value, $depth + 1);
                if ($nested !== []) {
                    $clean[$key] = $nested;
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (is_bool($left) || is_bool($right)) {
            return (bool) $left === (bool) $right;
        }

        return $left === $right;
    }
}
