<?php

namespace App\Policies;

use App\Models\TransactionTemplate;
use App\Models\User;

class TransactionTemplatePolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasAnyRole(['group_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, TransactionTemplate $template): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, TransactionTemplate $template): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, TransactionTemplate $template): bool
    {
        return $this->canManage($user);
    }

    public function generate(User $user): bool
    {
        return $this->canManage($user);
    }
}
