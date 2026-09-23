<?php

namespace App\Services\Admin;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates platform admin accounts.
 * The role is always admin. Super admin accounts are not managed here.
 */
final class PlatformAdminManager
{
    public function __construct(private PlatformAuditService $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, is_active: bool}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'role' => User::ROLE_ADMIN,
                'merchant_id' => null,
                'is_active' => $attributes['is_active'],
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $this->audit->record(
                PlatformAuditLog::ACTION_ADMIN_CREATED,
                null,
                $user,
                [],
                $this->audit->userSnapshot($user),
                'Created platform administrator '.$user->email.'.',
            );

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, is_active: bool}  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $this->guard($user);

        return DB::transaction(function () use ($user, $attributes): User {
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
                ? 'Updated platform administrator '.$user->email.' and reset the password.'
                : 'Updated platform administrator '.$user->email.'.';

            $this->audit->record(
                PlatformAuditLog::ACTION_ADMIN_UPDATED,
                null,
                $user,
                $old,
                $new,
                $description,
            );

            return $user;
        });
    }

    public function toggleActive(User $user): User
    {
        $this->guard($user);

        return DB::transaction(function () use ($user): User {
            $wasActive = (bool) $user->is_active;
            $user->forceFill([
                'is_active' => ! $wasActive,
            ])->save();

            $this->audit->record(
                $user->is_active
                    ? PlatformAuditLog::ACTION_ADMIN_ENABLED
                    : PlatformAuditLog::ACTION_ADMIN_DISABLED,
                null,
                $user,
                ['is_active' => $wasActive],
                ['is_active' => (bool) $user->is_active],
                ($user->is_active ? 'Enabled platform administrator ' : 'Disabled platform administrator ').$user->email.'.',
            );

            return $user;
        });
    }

    public function find(int $userId): User
    {
        $user = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereNull('merchant_id')
            ->whereKey($userId)
            ->first();

        if (! $user instanceof User || ! $user->isAdmin()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }

        return $user;
    }

    private function guard(User $user): void
    {
        if (! $user->isAdmin() || $user->merchant_id !== null || $user->isSuperAdmin()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$user->getKey()]);
        }
    }
}
