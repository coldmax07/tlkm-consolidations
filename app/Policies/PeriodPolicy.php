<?php

namespace App\Policies;

use App\Models\Period;
use App\Models\User;

class PeriodPolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasAnyRole(['group_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Period $period): bool
    {
        return $this->canManage($user);
    }

    public function lock(User $user, Period $period): bool
    {
        return $this->canManage($user);
    }

    public function unlock(User $user, Period $period): bool
    {
        return $this->canManage($user);
    }
}
