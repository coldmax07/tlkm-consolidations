<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\IcTransaction;
use App\Models\Period;
use App\Models\PeriodStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = request()->user();
        $isAdmin = $user?->hasRole('group_admin');
        $currentCompanyId = $isAdmin ? request()->integer('company_id') : $user?->company_id;

        $period = $this->activePeriod();
        if (! $period) {
            return response()->json(['message' => 'No active (open/unlocked) period found.'], 422);
        }

        if (! $currentCompanyId) {
            if ($isAdmin) {
                return response()->json([
                    'requires_company' => true,
                    'meta' => ['is_admin' => true, 'companies' => $this->companiesList()],
                    'period' => $this->periodPayload($period),
                ]);
            }

            return response()->json(['message' => 'No company assigned to your profile.'], 403);
        }

        $company = Company::findOrFail($currentCompanyId);
        $periodPayload = $this->periodPayload($period);

        $transactions = IcTransaction::with([
            'legs.legRole',
            'legs.legNature',
            'legs.status',
            'legs.agreementStatus',
            'legs.company',
            'legs.account',
            'senderCompany',
            'receiverCompany',
        ])
            ->where('period_id', $period->id)
            ->where(function ($q) use ($currentCompanyId) {
                $q->where('sender_company_id', $currentCompanyId)
                  ->orWhere('receiver_company_id', $currentCompanyId);
            })
            ->get();

        $population = $transactions->count();
        $completed = $transactions->filter(function ($tx) {
            $senderLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
            $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');

            return $senderLeg
                && $receiverLeg
                && $senderLeg->status?->name === 'REVIEWED'
                && $receiverLeg->status?->name === 'REVIEWED'
                && $receiverLeg->agreementStatus
                && $receiverLeg->agreementStatus->name !== 'UNKNOWN';
        })->count();

        $statusCounts = $transactions->flatMap(function ($tx) {
            return $tx->legs->map(fn ($leg) => $leg->status?->name ?? 'UNKNOWN');
        })->countBy()->toArray();

        $agreementCounts = $transactions->map(function ($tx) {
            $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');
            return $receiverLeg?->agreementStatus?->name ?? 'UNKNOWN';
        })->countBy()->toArray();

        $varianceSummary = $this->varianceSummary($transactions, $currentCompanyId);

        $entities = $this->entitySummary($period->id, $currentCompanyId);
        $ageing = $this->ageingSummary($transactions);
        $trend = $this->completionTrend($currentCompanyId);
        $exposure = $this->netExposure($period->id, $currentCompanyId);
        $accounts = $this->topAccounts($period->id, $currentCompanyId);

        return response()->json([
            'meta' => [
                'is_admin' => $isAdmin,
                'companies' => $isAdmin ? $this->companiesList() : [],
            ],
            'period' => $periodPayload,
            'current_company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ],
            'completion' => [
                'population' => $population,
                'completed' => $completed,
                'outstanding' => max(0, $population - $completed),
                'status_counts' => $statusCounts,
                'agreement_counts' => $agreementCounts,
                'variance' => $varianceSummary,
            ],
            'entities' => $entities,
            'ageing' => $ageing,
            'trend' => $trend,
            'exposure' => $exposure,
            'top_accounts' => $accounts,
        ]);
    }

    protected function activePeriod(): ?Period
    {
        $openStatusId = PeriodStatus::where('name', 'OPEN')->value('id');

        return Period::query()
            ->when($openStatusId, fn ($query) => $query->where('status_id', $openStatusId))
            ->whereNull('locked_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
    }

    protected function periodPayload(Period $period): array
    {
        return [
            'id' => $period->id,
            'label' => $period->label,
            'period_number' => $period->period_number,
            'year' => $period->year,
            'month' => $period->month,
        ];
    }

    protected function companiesList()
    {
        return Company::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ]);
    }

    protected function entitySummary(int $periodId, int $currentCompanyId)
    {
        $statements = FinancialStatement::whereIn('name', ['BALANCE_SHEET', 'INCOME_STATEMENT'])
            ->pluck('id', 'name');

        $transactions = IcTransaction::with([
            'legs.legRole',
            'legs.legNature',
            'legs.company',
        ])
            ->where('period_id', $periodId)
            ->where(function ($q) use ($currentCompanyId) {
                $q->where('sender_company_id', $currentCompanyId)
                  ->orWhere('receiver_company_id', $currentCompanyId);
            })
            ->get();

        $byCompany = [];

        foreach ($transactions as $tx) {
            foreach (['sender_company_id', 'receiver_company_id'] as $roleKey) {
                $cid = $tx->{$roleKey};
                if (! $cid) {
                    continue;
                }
                if (! isset($byCompany[$cid])) {
                    $byCompany[$cid] = [
                        'id' => $cid,
                        'name' => $tx->senderCompany?->id === $cid ? $tx->senderCompany?->name : $tx->receiverCompany?->name,
                        'population' => 0,
                        'completed' => 0,
                        'bs_abs' => 0,
                        'is_abs' => 0,
                    ];
                }

                $byCompany[$cid]['population']++;

                $senderLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
                $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');

                $isCompleted = $senderLeg
                    && $receiverLeg
                    && $senderLeg->status?->name === 'REVIEWED'
                    && $receiverLeg->status?->name === 'REVIEWED'
                    && $receiverLeg->agreementStatus
                    && $receiverLeg->agreementStatus->name !== 'UNKNOWN';

                if ($isCompleted) {
                    $byCompany[$cid]['completed']++;
                }

                foreach ($tx->legs as $leg) {
                    if ($leg->company_id !== $cid) {
                        continue;
                    }

                    $amountAbs = abs((float) ($leg->amount ?? 0));
                    if ($tx->financial_statement_id === ($statements['BALANCE_SHEET'] ?? null)) {
                        $byCompany[$cid]['bs_abs'] += $amountAbs;
                    } elseif ($tx->financial_statement_id === ($statements['INCOME_STATEMENT'] ?? null)) {
                        $byCompany[$cid]['is_abs'] += $amountAbs;
                    }
                }
            }
        }

        return collect($byCompany)->map(function ($row) {
            $population = $row['population'] ?: 1;
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'population' => $row['population'],
                'completed' => $row['completed'],
                'completion_pct' => round(($row['completed'] / $population) * 100, 1),
                'bs_volume_abs' => round($row['bs_abs'], 2),
                'is_volume_abs' => round($row['is_abs'], 2),
            ];
        })->values();
    }

    protected function varianceSummary($transactions, int $currentCompanyId): array
    {
        $zero = 0;
        $nonZero = 0;
        $items = [];

        foreach ($transactions as $tx) {
            $senderLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
            $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');

            if (! $senderLeg || ! $receiverLeg) {
                continue;
            }

            $variance = (float) (($senderLeg->amount ?? 0) - ($receiverLeg->amount ?? 0));
            if (abs($variance) < 0.0001) {
                $zero++;
            } else {
                $nonZero++;
            }

            $currentLeg = $tx->legs->firstWhere('company_id', $currentCompanyId);
            $otherLeg = $tx->legs->firstWhere(fn ($leg) => $leg->company_id !== $currentCompanyId);

            $items[] = [
                'transaction_id' => $tx->id,
                'trading_partner' => $otherLeg?->company?->name ?? $tx->receiverCompany?->name ?? $tx->senderCompany?->name,
                'hfm_account' => $currentLeg?->account?->name ?? $senderLeg->account?->name,
                'variance' => round($variance, 2),
            ];
        }

        $total = max(1, $zero + $nonZero);

        $top = collect($items)
            ->sortByDesc(fn ($i) => abs($i['variance'] ?? 0))
            ->take(5)
            ->values();

        return [
            'zero' => $zero,
            'non_zero' => $nonZero,
            'zero_pct' => round(($zero / $total) * 100, 1),
            'top' => $top,
        ];
    }

    protected function ageingSummary($transactions): array
    {
        $draftToSender = [];
        $senderToReceiver = [];

        foreach ($transactions as $tx) {
            $senderLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
            $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');
            if (! $senderLeg || ! $receiverLeg) {
                continue;
            }

            // Assuming prepared_at/ reviewed_at mark transitions
            if ($senderLeg->prepared_at && $senderLeg->reviewed_at) {
                $draftToSender[] = $senderLeg->prepared_at->diffInDays($senderLeg->reviewed_at);
            }
            if ($receiverLeg->prepared_at && $receiverLeg->reviewed_at) {
                $senderToReceiver[] = $receiverLeg->prepared_at->diffInDays($receiverLeg->reviewed_at);
            }
        }

        $avg = function ($arr) {
            return count($arr) ? round(array_sum($arr) / count($arr), 1) : 0;
        };

        return [
            'draft_to_sender_reviewed' => [
                'average_days' => $avg($draftToSender),
                'samples' => count($draftToSender),
            ],
            'sender_to_receiver_reviewed' => [
                'average_days' => $avg($senderToReceiver),
                'samples' => count($senderToReceiver),
            ],
        ];
    }

    protected function completionTrend(int $currentCompanyId): array
    {
        // last 6 periods
        $periods = Period::whereHas('status', fn ($q) => $q)->orderByDesc('year')->orderByDesc('month')->limit(6)->get();
        $periods = $periods->sortBy(fn ($p) => sprintf('%04d-%02d', $p->year, $p->month))->values();

        $points = [];
        foreach ($periods as $period) {
            $transactions = IcTransaction::with(['legs.legRole', 'legs.status', 'legs.agreementStatus'])
                ->where('period_id', $period->id)
                ->where(function ($q) use ($currentCompanyId) {
                    $q->where('sender_company_id', $currentCompanyId)
                      ->orWhere('receiver_company_id', $currentCompanyId);
                })
                ->get();

            $pop = $transactions->count();
            $completed = $transactions->filter(function ($tx) {
                $senderLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
                $receiverLeg = $tx->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');
                return $senderLeg
                    && $receiverLeg
                    && $senderLeg->status?->name === 'REVIEWED'
                    && $receiverLeg->status?->name === 'REVIEWED'
                    && $receiverLeg->agreementStatus
                    && $receiverLeg->agreementStatus->name !== 'UNKNOWN';
            })->count();

            $pct = $pop ? round(($completed / $pop) * 100, 1) : 0;
            $points[] = [
                'label' => $period->label,
                'completion_pct' => $pct,
                'population' => $pop,
                'completed' => $completed,
            ];
        }

        return $points;
    }

    protected function netExposure(int $periodId, int $currentCompanyId): array
    {
        $statements = FinancialStatement::whereIn('name', ['BALANCE_SHEET', 'INCOME_STATEMENT'])
            ->pluck('id', 'name');

        $transactions = IcTransaction::with(['legs.legRole', 'legs.legNature'])
            ->where('period_id', $periodId)
            ->where(function ($q) use ($currentCompanyId) {
                $q->where('sender_company_id', $currentCompanyId)
                  ->orWhere('receiver_company_id', $currentCompanyId);
            })
            ->get();

        $bsNet = 0;
        $isNet = 0;

        foreach ($transactions as $tx) {
            foreach ($tx->legs as $leg) {
                if ($leg->company_id !== $currentCompanyId) {
                    continue;
                }
                $amount = (float) ($leg->amount ?? 0);
                if ($tx->financial_statement_id === ($statements['BALANCE_SHEET'] ?? null)) {
                    $nature = $leg->legNature?->name;
                    if ($nature === 'RECEIVABLE') {
                        $bsNet += $amount;
                    } elseif ($nature === 'PAYABLE') {
                        $bsNet -= $amount;
                    }
                } elseif ($tx->financial_statement_id === ($statements['INCOME_STATEMENT'] ?? null)) {
                    $nature = $leg->legNature?->name;
                    if ($nature === 'REVENUE') {
                        $isNet += $amount;
                    } elseif ($nature === 'EXPENSE') {
                        $isNet -= $amount;
                    }
                }
            }
        }

        return [
            'balance_sheet_net' => round($bsNet, 2),
            'income_statement_net' => round($isNet, 2),
        ];
    }

    protected function topAccounts(int $periodId, int $currentCompanyId): array
    {
        $statementMap = FinancialStatement::whereIn('name', ['BALANCE_SHEET', 'INCOME_STATEMENT'])
            ->pluck('name', 'id');

        $rows = IcTransaction::query()
            ->select([
                'ic_transactions.financial_statement_id',
                'hfm_accounts.name as account_name',
                DB::raw('SUM(ABS(ic_transaction_legs.amount)) as total_abs'),
            ])
            ->join('ic_transaction_legs', 'ic_transaction_legs.ic_transaction_id', '=', 'ic_transactions.id')
            ->join('hfm_accounts', 'hfm_accounts.id', '=', 'ic_transaction_legs.hfm_account_id')
            ->where('ic_transactions.period_id', $periodId)
            ->where(function ($q) use ($currentCompanyId) {
                $q->where('ic_transactions.sender_company_id', $currentCompanyId)
                  ->orWhere('ic_transactions.receiver_company_id', $currentCompanyId);
            })
            ->groupBy('ic_transactions.financial_statement_id', 'hfm_accounts.name')
            ->orderByDesc(DB::raw('SUM(ABS(ic_transaction_legs.amount))'))
            ->limit(10)
            ->get();

        $grouped = $rows->groupBy('financial_statement_id')->mapWithKeys(function ($group, $statementId) use ($statementMap) {
            $key = $statementMap[$statementId] ?? $statementId;
            return [$key => $group->map(fn ($row) => [
                'account' => $row->account_name,
                'total_abs' => round((float) $row->total_abs, 2),
            ])->values()];
        });

        return [
            'BALANCE_SHEET' => $grouped['BALANCE_SHEET'] ?? [],
            'INCOME_STATEMENT' => $grouped['INCOME_STATEMENT'] ?? [],
        ];
    }
}
