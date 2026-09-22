<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates merchant users for a single merchant.
 */
final class MerchantUserManager
{
    public function __construct(private PlatformAuditService $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, is_active: bool}  $attributes
     */
    public function create(Merchant $merchant, array $attributes): User
    {
        return DB::transaction(function () use ($merchant, $attributes): User {
            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'role' => User::ROLE_MERCHANT_USER,
                'merchant_id' => $merchant->id,
                'is_active' => $attributes['is_active'],
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'onboarding_gateways_at' => now(),
                'onboarding_completed_at' => now(),
            ])->save();

            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_USER_CREATED,
                $merchant,
                $user,
                [],
                $this->audit->userSnapshot($user),
                'Created merchant user '.$user->email.'.',
            );

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, is_active: bool}  $attributes
     */
    public function update(Merchant $merchant, User $user, array $attributes): User
    {
        $this->guard($merchant, $user);

        return DB::transaction(function () use ($merchant, $user, $attributes): User {
            $before = $this->audit->userSnapshot($user);
            $passwordChanged = filled($attributes['password'] ?? null);

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'is_active' => $attributes['is_active'],
            ]);

            if ($passwordChanged) {
                $user->password = $attributes['password'];
            }

            $user->save();

            [$old, $new] = $this->audit->changes($before, $this->audit->userSnapshot($user));

            if ($old === [] && $new === [] && ! $passwordChanged) {
                return $user;
            }

            $description = $passwordChanged
                ? 'Updated merchant user '.$user->email.' and reset the password.'
                : 'Updated merchant user '.$user->email.'.';

            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_USER_UPDATED,
                $merchant,
                $user,
                $old,
                $new,
                $description,
            );

            return $user;
        });
    }

    public function toggleActive(Merchant $merchant, User $user): User
    {
        $this->guard($merchant, $user);

        return DB::transaction(function () use ($merchant, $user): User {
            $wasActive = (bool) $user->is_active;
            $user->forceFill([
                'is_active' => ! $wasActive,
            ])->save();

            $this->audit->record(
                $user->is_active
                    ? PlatformAuditLog::ACTION_MERCHANT_USER_ENABLED
                    : PlatformAuditLog::ACTION_MERCHANT_USER_DISABLED,
                $merchant,
                $user,
                ['is_active' => $wasActive],
                ['is_active' => (bool) $user->is_active],
                ($user->is_active ? 'Enabled merchant user ' : 'Disabled merchant user ').$user->email.'.',
            );

            return $user;
        });
    }

    public function find(Merchant $merchant, int $userId): User
    {
        $user = $merchant->users()
            ->where('role', User::ROLE_MERCHANT_USER)
            ->whereKey($userId)
            ->first();

        if (! $user instanceof User || $user->isPlatformOperator()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }

        return $user;
    }

    private function guard(Merchant $merchant, User $user): void
    {
        if (
            ! $user->isMerchantUser()
            || $user->isPlatformOperator()
            || (int) $user->merchant_id !== (int) $merchant->id
        ) {
            throw (new ModelNotFoundException)->setModel(User::class, [$user->getKey()]);
        }
    }
}
