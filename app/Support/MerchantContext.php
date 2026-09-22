<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Admin\PlatformAuditService;

/**
 * Resolves the merchant for the current request.
 * Merchant users use their own merchant_id. Platform admins use a session id set only by Access Merchant.
 */
final class MerchantContext
{
    public const SESSION_ID = 'accessed_merchant_id';

    public const SESSION_AT = 'accessed_merchant_at';

    public function __construct(private PlatformAuditService $audit) {}

    public function id(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isMerchantUser()) {
            return $user->merchant_id === null ? null : (int) $user->merchant_id;
        }

        if ($user->isPlatformOperator()) {
            return $this->accessedMerchant()?->id;
        }

        return null;
    }

    public function merchant(): ?Merchant
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isMerchantUser()) {
            return $user->merchant;
        }

        if ($user->isPlatformOperator()) {
            return $this->accessedMerchant();
        }

        return null;
    }

    public function enter(User $actor, Merchant $merchant): void
    {
        if (! $actor->isPlatformOperator() || ! $merchant->is_active) {
            abort(403);
        }

        $currentId = $this->rawId();

        if ($currentId !== null && $currentId !== (int) $merchant->id) {
            $previous = Merchant::query()->find($currentId);
            if ($previous instanceof Merchant) {
                $this->audit->record(
                    PlatformAuditLog::ACTION_MERCHANT_CONTEXT_EXITED,
                    $previous,
                    $previous,
                    [],
                    ['name' => $previous->name],
                    'Super Admin left merchant context.',
                );
            }
        }

        if ($currentId === (int) $merchant->id) {
            return;
        }

        session()->put(self::SESSION_ID, (int) $merchant->id);
        session()->put(self::SESSION_AT, now()->toIso8601String());

        $this->audit->record(
            PlatformAuditLog::ACTION_MERCHANT_CONTEXT_ENTERED,
            $merchant,
            $merchant,
            [],
            [
                'name' => $merchant->name,
                'is_active' => true,
            ],
            'Super Admin entered merchant context.',
        );
    }

    public function exit(User $actor): void
    {
        if (! $actor->isPlatformOperator()) {
            abort(403);
        }

        $currentId = $this->rawId();
        $merchant = $currentId === null ? null : Merchant::query()->find($currentId);
        $this->forget();

        if ($merchant instanceof Merchant) {
            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_CONTEXT_EXITED,
                $merchant,
                $merchant,
                [],
                ['name' => $merchant->name],
                'Super Admin left merchant context.',
            );
        }
    }

    public function forget(): void
    {
        session()->forget([self::SESSION_ID, self::SESSION_AT]);
    }

    private function accessedMerchant(): ?Merchant
    {
        $id = $this->rawId();

        if ($id === null) {
            if (session()->has(self::SESSION_ID)) {
                $this->forget();
            }

            return null;
        }

        $merchant = Merchant::query()->find($id);

        if (! $merchant instanceof Merchant || ! $merchant->is_active) {
            $this->forget();

            return null;
        }

        return $merchant;
    }

    private function rawId(): ?int
    {
        $id = session(self::SESSION_ID);

        if (! is_numeric($id)) {
            return null;
        }

        return (int) $id;
    }
}
