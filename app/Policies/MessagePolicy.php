<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function create(User $user, Message $message): bool
    {
        return $this->userInTransaction($user, $message);
    }

    public function view(User $user, Message $message): bool
    {
        return $this->userInTransaction($user, $message);
    }

    protected function userInTransaction(User $user, Message $message): bool
    {
        if ($user->hasRole('group_admin')) {
            return true;
        }

        $transaction = $message->thread?->transaction;

        if (! $transaction) {
            return false;
        }

        return in_array($user->company_id, [
            $transaction->sender_company_id,
            $transaction->receiver_company_id,
        ], true);
    }
}
