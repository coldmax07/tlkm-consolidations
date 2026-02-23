<?php

namespace App\Services;

use App\Models\IcLegStatusHistory;
use App\Models\IcTransactionLeg;
use App\Models\LegStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(protected AgreementService $agreementService)
    {
    }

    public function updateSenderAmount(IcTransactionLeg $leg, User $user, float $amount, ?float $adjustmentAmount = null): IcTransactionLeg
    {
        $this->ensureSenderLeg($leg);
        $this->ensureDraftOrRejected($leg);

        $leg->update([
            'amount' => $amount,
            'adjustment_amount' => $adjustmentAmount ?? 0,
        ]);

        $receiverLeg = $leg->transaction
            ->legs()
            ->whereHas('legRole', fn ($q) => $q->where('name', 'RECEIVER'))
            ->first();

        if ($receiverLeg && $receiverLeg->status?->name === 'DRAFT') {
            $receiverLeg->update(['amount' => $amount]);
        }

        return $leg->fresh('status', 'legRole');
    }

    public function submit(IcTransactionLeg $leg, User $user): IcTransactionLeg
    {
        $this->ensureSenderLeg($leg);

        return DB::transaction(function () use ($leg, $user) {
            $nextStatus = $this->getStatusId('PENDING_REVIEW');

            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Submitted for review');

            $leg->update([
                'status_id' => $nextStatus,
                'prepared_by_id' => $user->id,
                'prepared_at' => now(),
            ]);

            return $leg->fresh(['status', 'legRole']);
        });
    }

    public function approve(IcTransactionLeg $leg, User $user): IcTransactionLeg
    {
        $this->ensureSenderLeg($leg);

        return DB::transaction(function () use ($leg, $user) {
            $nextStatus = $this->getStatusId('REVIEWED');
            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Approved');

            $leg->update([
                'status_id' => $nextStatus,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            return $leg->fresh(['status', 'legRole']);
        });
    }

    public function reject(IcTransactionLeg $leg, User $user, string $reason): IcTransactionLeg
    {
        $this->ensureSenderLeg($leg);

        return DB::transaction(function () use ($leg, $user, $reason) {
            $nextStatus = $this->getStatusId('REJECTED');
            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Rejected: '.$reason);

            $leg->update([
                'status_id' => $nextStatus,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            return $leg->fresh(['status', 'legRole']);
        });
    }

    public function updateReceiver(IcTransactionLeg $leg, User $user, float $amount, int $agreementStatusId, ?string $reason): IcTransactionLeg
    {
        $this->ensureReceiverLeg($leg);
        $this->ensureDraftOrRejected($leg);

        $this->agreementService->validateReceiverInput($leg, $amount, $agreementStatusId, $reason);

        $cleanReason = $reason;
        $agreement = $leg->agreementStatus()->getRelated()->find($agreementStatusId);
        if ($agreement && $agreement->name === 'AGREE') {
            $cleanReason = null;
        }

        $leg->update([
            'amount' => $amount,
            'agreement_status_id' => $agreementStatusId,
            'disagree_reason' => $cleanReason,
        ]);

        return $leg->fresh(['status', 'legRole', 'agreementStatus']);
    }

    public function submitReceiver(IcTransactionLeg $leg, User $user): IcTransactionLeg
    {
        $this->ensureReceiverLeg($leg);
        $leg->loadMissing('agreementStatus');
        $this->agreementService->ensureReceiverDecisionReady($leg);
        $this->ensureEditableStatus($leg, ['DRAFT', 'REJECTED']);

        return DB::transaction(function () use ($leg, $user) {
            $nextStatus = $this->getStatusId('PENDING_REVIEW');
            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Receiver submitted for review');

            $leg->update([
                'status_id' => $nextStatus,
                'prepared_by_id' => $user->id,
                'prepared_at' => now(),
            ]);

            return $leg->fresh(['status', 'legRole', 'agreementStatus']);
        });
    }

    public function approveReceiver(IcTransactionLeg $leg, User $user): IcTransactionLeg
    {
        $this->ensureReceiverLeg($leg);
        $leg->loadMissing('agreementStatus');
        $this->agreementService->ensureReceiverDecisionReady($leg);
        $this->ensureEditableStatus($leg, ['PENDING_REVIEW']);

        return DB::transaction(function () use ($leg, $user) {
            $nextStatus = $this->getStatusId('REVIEWED');
            $senderAmount = $leg->transaction
                ->legs()
                ->whereHas('legRole', fn ($q) => $q->where('name', 'SENDER'))
                ->value('amount');

            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Receiver approved');

            $leg->update([
                'status_id' => $nextStatus,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
                'counterparty_amount_snapshot' => $senderAmount,
            ]);

            return $leg->fresh(['status', 'legRole', 'agreementStatus']);
        });
    }

    public function rejectReceiver(IcTransactionLeg $leg, User $user, string $reason): IcTransactionLeg
    {
        $this->ensureReceiverLeg($leg);
        $leg->loadMissing('agreementStatus');
        $this->agreementService->ensureReceiverDecisionReady($leg);
        $this->ensureEditableStatus($leg, ['PENDING_REVIEW']);

        return DB::transaction(function () use ($leg, $user, $reason) {
            $nextStatus = $this->getStatusId('REJECTED');
            $this->recordHistory($leg, $leg->status_id, $nextStatus, $user, 'Receiver rejected: '.$reason);

            $leg->update([
                'status_id' => $nextStatus,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
                'disagree_reason' => $reason,
            ]);

            return $leg->fresh(['status', 'legRole', 'agreementStatus']);
        });
    }

    protected function recordHistory(IcTransactionLeg $leg, ?int $fromStatus, int $toStatus, User $user, ?string $note = null): void
    {
        IcLegStatusHistory::create([
            'ic_transaction_leg_id' => $leg->id,
            'from_status_id' => $fromStatus,
            'to_status_id' => $toStatus,
            'changed_by_id' => $user->id,
            'note' => $note,
        ]);
    }

    protected function getStatusId(string $name): int
    {
        $id = LegStatus::where('name', $name)->value('id');

        if (! $id) {
            throw new InvalidArgumentException("Status {$name} is not configured.");
        }

        return $id;
    }

    protected function ensureSenderLeg(IcTransactionLeg $leg): void
    {
        if ($leg->legRole?->name !== 'SENDER') {
            throw new InvalidArgumentException('Action only allowed on sender legs.');
        }
    }

    protected function ensureReceiverLeg(IcTransactionLeg $leg): void
    {
        if ($leg->legRole?->name !== 'RECEIVER') {
            throw new InvalidArgumentException('Action only allowed on receiver legs.');
        }
    }

    protected function ensureDraftOrRejected(IcTransactionLeg $leg): void
    {
        if (! in_array($leg->status?->name, ['DRAFT', 'REJECTED'], true)) {
            throw new InvalidArgumentException('Leg is not editable in the current status.');
        }
    }

    protected function ensureEditableStatus(IcTransactionLeg $leg, array $allowedStatuses): void
    {
        if (! in_array($leg->status?->name, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Leg is not editable in the current status.');
        }
    }
}
