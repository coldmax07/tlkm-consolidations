<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectLegRequest;
use App\Http\Requests\UpdateReceiverLegRequest;
use App\Http\Requests\UpdateSenderLegRequest;
use App\Models\IcTransactionLeg;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegWorkflowController extends Controller
{
    public function update(UpdateSenderLegRequest $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $updated = $service->updateSenderAmount($leg, $request->user(), (float) $request->input('amount'));

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function updateReceiver(UpdateReceiverLegRequest $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $updated = $service->updateReceiver(
            $leg,
            $request->user(),
            (float) $request->input('amount'),
            (int) $request->input('agreement_status_id'),
            $request->input('disagree_reason')
        );

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function submit(Request $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $this->authorize('submit', $leg);

        $updated = $service->submit($leg, $request->user());

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function submitReceiver(Request $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $this->authorize('submitReceiver', $leg);

        $updated = $service->submitReceiver($leg, $request->user());

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function approve(Request $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $this->authorize('review', $leg);

        $updated = $service->approve($leg, $request->user());

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function approveReceiver(Request $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $this->authorize('reviewReceiver', $leg);

        $updated = $service->approveReceiver($leg, $request->user());

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function reject(RejectLegRequest $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $updated = $service->reject($leg, $request->user(), $request->input('reason'));

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    public function rejectReceiver(RejectLegRequest $request, IcTransactionLeg $leg, WorkflowService $service): JsonResponse
    {
        $this->authorize('reviewReceiver', $leg);

        $updated = $service->rejectReceiver($leg, $request->user(), $request->input('reason'));

        return response()->json([
            'leg' => $this->transformLeg($updated),
        ]);
    }

    protected function transformLeg(IcTransactionLeg $leg): array
    {
        $leg->loadMissing([
            'status',
            'legRole',
            'company',
            'counterpartyCompany',
        ]);

        return [
            'id' => $leg->id,
            'company' => $leg->company?->name,
            'counterparty' => $leg->counterpartyCompany?->name,
            'status' => $leg->status?->display_label,
            'status_name' => $leg->status?->name,
            'amount' => $leg->amount !== null ? (float) $leg->amount : null,
            'role' => $leg->legRole?->name,
        ];
    }
}
