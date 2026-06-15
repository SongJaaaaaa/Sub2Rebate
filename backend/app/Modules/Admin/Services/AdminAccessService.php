<?php

namespace App\Modules\Admin\Services;

use App\Models\User;

class AdminAccessService
{
    public function isAdmin(?User $user): bool
    {
        return $user instanceof User && $user->role === 'admin' && $user->status === 'active';
    }
}
