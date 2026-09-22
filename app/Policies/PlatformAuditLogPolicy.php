<?php

namespace App\Policies;

use App\Models\PlatformAuditLog;
use App\Models\User;

class PlatformAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformOperator();
    }

    public function view(User $user, PlatformAuditLog $platformAuditLog): bool
    {
        return $user->isPlatformOperator();
    }
}
