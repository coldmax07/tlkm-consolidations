<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    protected function isGroupAdmin(User $user): bool
    {
        return $user->hasRole('group_admin');
    }

    protected function isCompanyAdmin(User $user): bool
    {
        return $user->hasRole('company_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isGroupAdmin($user) || $this->isCompanyAdmin($user);
    }

    public function view(User $user, User $target): bool
    {
        if ($this->isGroupAdmin($user)) {
            return true;
        }
        if ($this->isCompanyAdmin($user)) {
            return $user->company_id && $user->company_id === $target->company_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->isGroupAdmin($user) || $this->isCompanyAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        if ($this->isGroupAdmin($user)) {
            return true;
        }
        if ($this->isCompanyAdmin($user)) {
            return $user->company_id && $user->company_id === $target->company_id;
        }

        return false;
    }

    public function delete(User $user, User $target): bool
    {
        if ($this->isGroupAdmin($user)) {
            return true;
        }
        if ($this->isCompanyAdmin($user)) {
            return $user->company_id && $user->company_id === $target->company_id;
        }

        return false;
    }
}
