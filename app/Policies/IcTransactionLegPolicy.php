<?php

namespace App\Policies;

use App\Models\IcTransactionLeg;
use App\Models\User;

class IcTransactionLegPolicy
{
    protected function isGroupAdmin(User $user): bool
    {
        return $user->hasRole('group_admin');
    }

    protected function isCompanyAdmin(User $user): bool
    {
        return $user->hasRole('company_admin');
    }

    protected function isPreparer(User $user): bool
    {
        return $user->hasAnyRole(['company_preparer', 'company_user']);
    }

    protected function isReviewer(User $user): bool
    {
        return $user->hasAnyRole(['company_reviewer', 'company_user']);
    }

    protected function sameCompany(User $user, IcTransactionLeg $leg): bool
    {
        return $user->company_id && $user->company_id === $leg->company_id;
    }

    public function updateSender(User $user, IcTransactionLeg $leg): bool
    {
        if ($leg->legRole?->name !== 'SENDER') {
            return false;
        }

        $editable = in_array($leg->status?->name, ['DRAFT', 'REJECTED'], true);

        if ($this->isGroupAdmin($user)) {
            return $editable;
        }

        return $editable
            && ($this->isCompanyAdmin($user) || $this->isPreparer($user))
            && $this->sameCompany($user, $leg);
    }

    public function submit(User $user, IcTransactionLeg $leg): bool
    {
        return $this->updateSender($user, $leg);
    }

    public function review(User $user, IcTransactionLeg $leg): bool
    {
        $isSender = $leg->legRole?->name === 'SENDER';
        $pending = $leg->status?->name === 'PENDING_REVIEW';

        if ($this->isGroupAdmin($user)) {
            return $isSender && $pending;
        }

        return $isSender
            && $pending
            && ($this->isCompanyAdmin($user) || $this->isReviewer($user))
            && $this->sameCompany($user, $leg);
    }

    public function updateReceiver(User $user, IcTransactionLeg $leg): bool
    {
        $isReceiver = $leg->legRole?->name === 'RECEIVER';

        if (! $isReceiver) {
            return false;
        }

        $senderReviewed = $this->senderLegIsReviewed($leg);
        $editable = $senderReviewed && in_array($leg->status?->name, ['DRAFT', 'REJECTED'], true);

        if ($this->isGroupAdmin($user)) {
            return $editable;
        }

        return $editable
            && ($this->isCompanyAdmin($user) || $this->isPreparer($user))
            && $this->sameCompany($user, $leg)
            ;
    }

    public function submitReceiver(User $user, IcTransactionLeg $leg): bool
    {
        if ($leg->legRole?->name !== 'RECEIVER') {
            return false;
        }

        return $this->updateReceiver($user, $leg);
    }

    public function reviewReceiver(User $user, IcTransactionLeg $leg): bool
    {
        $isReceiver = $leg->legRole?->name === 'RECEIVER';
        $pending = $leg->status?->name === 'PENDING_REVIEW';

        if (! $isReceiver) {
            return false;
        }

        if ($this->isGroupAdmin($user)) {
            return $pending;
        }

        return $pending
            && ($this->isCompanyAdmin($user) || $this->isReviewer($user))
            && $this->sameCompany($user, $leg)
            ;
    }

    protected function senderLegIsReviewed(IcTransactionLeg $leg): bool
    {
        return $leg->transaction
            ->legs()
            ->whereHas('legRole', fn ($q) => $q->where('name', 'SENDER'))
            ->whereHas('status', fn ($q) => $q->where('name', 'REVIEWED'))
            ->exists();
    }
}
