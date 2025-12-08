<?php

namespace App\Services;

use App\Models\AgreementStatus;
use App\Models\IcTransactionLeg;
use Illuminate\Validation\ValidationException;

class AgreementService
{
    public function validateReceiverInput(IcTransactionLeg $receiverLeg, float $amount, int $agreementStatusId, ?string $reason): void
    {
        $agreement = AgreementStatus::find($agreementStatusId);

        if (! $agreement) {
            throw ValidationException::withMessages([
                'agreement_status_id' => 'Invalid agreement status selected.',
            ]);
        }

        if ($agreement->name === 'UNKNOWN') {
            throw ValidationException::withMessages([
                'agreement_status_id' => 'Select agree or disagree before continuing.',
            ]);
        }

        $senderLeg = $receiverLeg->transaction
            ->legs()
            ->whereHas('legRole', fn ($q) => $q->where('name', 'SENDER'))
            ->first();

        if (! $senderLeg) {
            throw ValidationException::withMessages([
                'agreement_status_id' => 'Sender leg missing for this transaction.',
            ]);
        }

        $senderAmount = (float) ($senderLeg->amount ?? 0);
        $receiverAmount = (float) $amount;

        if ($agreement->name === 'AGREE' && abs($receiverAmount - $senderAmount) > 0.0001) {
            throw ValidationException::withMessages([
                'agreement_status_id' => 'You can only agree if your amount matches the sender amount.',
            ]);
        }

        if ($agreement->name === 'AGREE' && filled($reason)) {
            throw ValidationException::withMessages([
                'disagree_reason' => 'Do not provide a reason when agreeing.',
            ]);
        }

        if ($agreement->name === 'DISAGREE' && blank($reason)) {
            throw ValidationException::withMessages([
                'disagree_reason' => 'Provide a reason when disagreeing with the counterparty amount.',
            ]);
        }
    }

    public function ensureReceiverDecisionReady(IcTransactionLeg $receiverLeg): void
    {
        $agreement = $receiverLeg->agreementStatus;

        if (! $agreement || $agreement->name === 'UNKNOWN') {
            throw ValidationException::withMessages([
                'agreement_status_id' => 'Select agree or disagree before continuing.',
            ]);
        }

        if ($agreement->name === 'AGREE' && filled($receiverLeg->disagree_reason)) {
            throw ValidationException::withMessages([
                'disagree_reason' => 'Do not provide a reason when agreeing.',
            ]);
        }

        if ($agreement->name === 'DISAGREE' && blank($receiverLeg->disagree_reason)) {
            throw ValidationException::withMessages([
                'disagree_reason' => 'Provide a reason when disagreeing with the counterparty amount.',
            ]);
        }
    }
}
