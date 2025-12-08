<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatementIndexRequest;
use App\Models\IcTransactionLeg;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Auth\Access\Gate;

class StatementController extends Controller
{
    public function index(StatementIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $isGroupAdmin = $user?->hasRole('group_admin');

        $filters = $request->validated();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(5, min((int) $request->input('per_page', 15), 100));
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortBy = $request->input('sort_by', 'sender_company');

        if (! $isGroupAdmin) {
            $companyId = $user?->company_id;
            abort_if(! $companyId, 403, 'No company assigned to your profile.');
            $filters['company_id'] = $companyId;
        }

        $period = Period::findOrFail($filters['period_id']);

        $legsQuery = IcTransactionLeg::query()
            ->with([
                'legRole',
                'legNature',
                'status',
                'agreementStatus',
                'company',
                'counterpartyCompany',
                'account.category',
                'transaction.senderCompany',
                'transaction.receiverCompany',
                'transaction',
            ])
            ->whereHas('transaction', function ($query) use ($filters) {
                $query->where('financial_statement_id', $filters['financial_statement_id'])
                    ->where('period_id', $filters['period_id']);

                if (! empty($filters['sender_company_id'])) {
                    $query->where('sender_company_id', $filters['sender_company_id']);
                }

                if (! empty($filters['receiver_company_id'])) {
                    $query->where('receiver_company_id', $filters['receiver_company_id']);
                }
            });

        if (! empty($filters['company_id'])) {
            $companyId = (int) $filters['company_id'];
            $legsQuery->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhere('counterparty_company_id', $companyId);
            });
        }

        if (! empty($filters['counterparty_company_id'])) {
            $legsQuery->where('counterparty_company_id', $filters['counterparty_company_id']);
        }

        if (! empty($filters['status_id'])) {
            $legsQuery->where('status_id', $filters['status_id']);
        }

        if (! empty($filters['agreement_status_id'])) {
            $legsQuery->where('agreement_status_id', $filters['agreement_status_id']);
        }

        if (! empty($filters['leg_role_id'])) {
            $legsQuery->where('leg_role_id', $filters['leg_role_id']);
        }

        if (! empty($filters['hfm_account_id'])) {
            $legsQuery->where('hfm_account_id', $filters['hfm_account_id']);
        }

        if (! empty($filters['account_category_id'])) {
            $legsQuery->whereHas('account', function ($query) use ($filters) {
                $query->where('account_category_id', $filters['account_category_id']);
            });
        }

        $legs = $legsQuery->get();

        $grouped = $legs->groupBy('ic_transaction_id');
        $gate = app(Gate::class)->forUser($request->user());

        $transactions = $grouped->map(function ($group) use ($gate) {
            /** @var \App\Models\IcTransactionLeg $sample */
            $sample = $group->first();
            $transaction = $sample?->transaction;

            $sender = $group->first(fn ($leg) => $leg->legRole?->name === 'SENDER');
            $receiver = $group->first(fn ($leg) => $leg->legRole?->name === 'RECEIVER');

            $senderAmount = (float) ($sender?->amount ?? 0);
            $receiverAmount = (float) ($receiver?->amount ?? 0);

            return [
                'transaction_id' => $transaction?->id,
                'template_id' => $transaction?->transaction_template_id,
                'currency' => $transaction?->currency,
                'sender_company' => [
                    'id' => $transaction?->senderCompany?->id,
                    'name' => $transaction?->senderCompany?->name,
                    'code' => $transaction?->senderCompany?->code,
                ],
                'receiver_company' => [
                    'id' => $transaction?->receiverCompany?->id,
                    'name' => $transaction?->receiverCompany?->name,
                    'code' => $transaction?->receiverCompany?->code,
                ],
                'legs' => [
                    'sender' => $sender ? $this->transformLeg($sender, $gate) : null,
                    'receiver' => $receiver ? $this->transformLeg($receiver, $gate) : null,
                ],
                'variance' => round($senderAmount - $receiverAmount, 2),
            ];
        })->values();

        $senderTotal = $transactions->sum(fn ($tx) => $tx['legs']['sender']['amount'] ?? 0);
        $receiverTotal = $transactions->sum(fn ($tx) => $tx['legs']['receiver']['amount'] ?? 0);

        $sorted = $transactions->sortBy(function ($tx) use ($sortBy) {
            return match ($sortBy) {
                'receiver_company' => strtolower($tx['receiver_company']['name'] ?? ''),
                'variance' => $tx['variance'] ?? 0,
                'currency' => $tx['currency'] ?? '',
                default => strtolower($tx['sender_company']['name'] ?? ''),
            };
        }, SORT_REGULAR, $sortDir === 'desc');

        $total = $sorted->count();
        $paginated = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'period' => [
                'id' => $period->id,
                'label' => $period->label,
                'period_number' => $period->period_number,
                'year' => $period->year,
                'month' => $period->month,
            ],
            'filters' => $filters,
            'totals' => [
                'sender' => round($senderTotal, 2),
                'receiver' => round($receiverTotal, 2),
                'variance' => round($senderTotal - $receiverTotal, 2),
                'transactions' => $total,
            ],
            'transactions' => $paginated,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => [
                'by' => $sortBy,
                'dir' => $sortDir,
            ],
        ]);
    }

    protected function transformLeg(IcTransactionLeg $leg, Gate $gate): array
    {
        return [
            'id' => $leg->id,
            'company' => [
                'id' => $leg->company?->id,
                'name' => $leg->company?->name,
                'code' => $leg->company?->code,
            ],
            'counterparty' => [
                'id' => $leg->counterpartyCompany?->id,
                'name' => $leg->counterpartyCompany?->name,
                'code' => $leg->counterpartyCompany?->code,
            ],
            'role' => [
                'id' => $leg->leg_role_id,
                'name' => $leg->legRole?->name,
                'label' => $leg->legRole?->display_label,
            ],
            'nature' => [
                'name' => $leg->legNature?->name,
                'label' => $leg->legNature?->display_label,
            ],
            'account' => [
                'id' => $leg->account?->id,
                'name' => $leg->account?->name,
                'category' => [
                    'id' => $leg->account?->category?->id,
                    'name' => $leg->account?->category?->name,
                    'label' => $leg->account?->category?->display_label,
                ],
            ],
            'status' => [
                'id' => $leg->status?->id,
                'name' => $leg->status?->name,
                'label' => $leg->status?->display_label,
            ],
            'agreement_status' => [
                'id' => $leg->agreementStatus?->id,
                'name' => $leg->agreementStatus?->name,
                'label' => $leg->agreementStatus?->display_label,
            ],
            'amount' => $leg->amount !== null ? (float) $leg->amount : null,
            'disagree_reason' => $leg->disagree_reason,
            'permissions' => $this->resolvePermissions($leg, $gate),
        ];
    }

    /**
     * Mirror policy checks so the UI can render controls accurately.
     */
    protected function resolvePermissions(IcTransactionLeg $leg, Gate $gate): array
    {
        $role = $leg->legRole?->name;

        if ($role === 'SENDER') {
            return [
                'edit' => $gate->allows('updateSender', $leg),
                'submit' => $gate->allows('submit', $leg),
                'review' => $gate->allows('review', $leg),
            ];
        }

        if ($role === 'RECEIVER') {
            return [
                'edit' => $gate->allows('updateReceiver', $leg),
                'submit' => $gate->allows('submitReceiver', $leg),
                'review' => $gate->allows('reviewReceiver', $leg),
            ];
        }

        return [];
    }
}
