<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;

class FiscalYearPolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasAnyRole(['group_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->canManage($user);
    }
}
