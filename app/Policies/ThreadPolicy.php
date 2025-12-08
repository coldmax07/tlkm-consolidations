<?php

namespace App\Policies;

use App\Models\Thread;
use App\Models\User;

class ThreadPolicy
{
    public function view(User $user, Thread $thread): bool
    {
        return $this->userInTransaction($user, $thread);
    }

    public function update(User $user, Thread $thread): bool
    {
        return $this->userInTransaction($user, $thread);
    }

    protected function userInTransaction(User $user, Thread $thread): bool
    {
        if ($user->hasRole('group_admin')) {
            return true;
        }

        $transaction = $thread->transaction;

        if (! $transaction) {
            return false;
        }

        return in_array($user->company_id, [
            $transaction->sender_company_id,
            $transaction->receiver_company_id,
        ], true);
    }
}
