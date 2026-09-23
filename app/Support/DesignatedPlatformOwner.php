<?php

namespace App\Support;

use App\Models\User;

/**
 * The single platform owner designated by configuration.
 * Other admin accounts are left unchanged.
 */
final class DesignatedPlatformOwner
{
    public const DEFAULT_EMAIL = 'admin@example.com';

    public static function email(): string
    {
        $email = config('auth.super_admin.email');

        if (! is_string($email) || trim($email) === '') {
            return self::DEFAULT_EMAIL;
        }

        return trim($email);
    }

    /**
     * Promote only an existing admin with the designated email.
     * Does not change passwords or any other account.
     */
    public static function promoteExistingAdmin(): int
    {
        return User::query()
            ->where('email', self::email())
            ->where('role', User::ROLE_ADMIN)
            ->update([
                'role' => User::ROLE_SUPER_ADMIN,
                'merchant_id' => null,
            ]);
    }
}
