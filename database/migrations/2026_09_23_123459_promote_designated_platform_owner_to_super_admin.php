<?php

use App\Models\User;
use App\Support\DesignatedPlatformOwner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Promote only the designated platform owner from admin to super_admin.
     * Other admin accounts, passwords, and historical records stay unchanged.
     */
    public function up(): void
    {
        DesignatedPlatformOwner::promoteExistingAdmin();
    }

    /**
     * Return the designated account to the admin role.
     * Other super admin accounts are left unchanged.
     */
    public function down(): void
    {
        User::query()
            ->where('email', DesignatedPlatformOwner::email())
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->update([
                'role' => User::ROLE_ADMIN,
            ]);
    }
};
