<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIndexRequest;
use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\IcTransaction;
use App\Models\Period;
use App\Models\PeriodStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConfirmationReportExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __invoke(ReportIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole('group_admin');
        $statementName = $request->input('statement');

        $statement = FinancialStatement::where('name', $statementName)->firstOrFail();
        $period = $this->activePeriod();

        if (! $period) {
            return response()->json([
                'message' => 'No active (open/unlocked) period found.',
                'data' => [],
            ], 422);
        }

        $currentCompanyId = $request->integer('company_id');

        if ($isAdmin) {
            if (! $currentCompanyId) {
                return response()->json([
                    'requires_company' => true,
                    'meta' => [
                        'is_admin' => true,
                        'companies' => $this->companiesList(),
                    ],
                    'period' => $period ? [
                        'id' => $period->id,
                        'label' => $period->label,
                        'year' => $period->year,
                        'month' => $period->month,
                    ] : null,
                    'totals' => [
                        'receivable' => 0,
                        'payable' => 0,
                        'revenue' => 0,
                        'expense' => 0,
                        'variance' => 0,
                        'transactions' => 0,
                    ],
                    'rows' => [],
                    'current_company' => null,
                    'message' => 'Select a company to view reports.',
                ]);
            }
        } else {
            $currentCompanyId = $user?->company_id;
            if (! $currentCompanyId) {
                return response()->json([
                    'message' => 'No company assigned to your profile.',
                ], 403);
            }
        }

        $currentCompany = Company::findOrFail($currentCompanyId);

        $transactions = IcTransaction::query()
            ->with([
                'senderCompany',
                'receiverCompany',
                'template',
                'legs.legRole',
                'legs.legNature',
                'legs.status',
                'legs.agreementStatus',
                'legs.account',
                'legs.company',
                'legs.counterpartyCompany',
                'legs.preparedBy',
                'legs.reviewedBy',
            ])
            ->where('period_id', $period->id)
            ->where('financial_statement_id', $statement->id)
            ->where(function ($query) use ($currentCompanyId) {
                $query->where('sender_company_id', $currentCompanyId)
                    ->orWhere('receiver_company_id', $currentCompanyId);
            })
            ->orderBy('id')
            ->get();

        $rows = collect();
        $totals = [
            'receivable' => 0.0,
            'payable' => 0.0,
            'revenue' => 0.0,
            'expense' => 0.0,
            'current_total' => 0.0,
            'counterparty_total' => 0.0,
        ];

        foreach ($transactions as $transaction) {
            $senderLeg = $transaction->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'SENDER');
            $receiverLeg = $transaction->legs->firstWhere(fn ($leg) => $leg->legRole?->name === 'RECEIVER');
            $currentLeg = $transaction->legs->firstWhere('company_id', $currentCompanyId);
            $otherLeg = $transaction->legs->firstWhere(fn ($leg) => $leg->company_id !== $currentCompanyId);

            if (! $currentLeg) {
                continue;
            }

            $amount = (float) ($currentLeg->amount ?? 0);
            $nature = $currentLeg->legNature?->name;
            $accountName = $currentLeg->account?->name;
            $categoryLabel = $currentLeg->account?->category?->display_label
                ?? $senderLeg?->account?->category?->display_label
                ?? null;
            $tradingPartner = $otherLeg?->company?->name ?? $transaction->receiverCompany?->name;
            $agreementLabel = $receiverLeg?->agreementStatus?->display_label;
            $agreementName = $receiverLeg?->agreementStatus?->name;
            $currentAmount = (float) ($currentLeg->amount ?? 0);
            $counterpartyAmount = (float) ($otherLeg?->amount ?? 0);
            $rowVariance = round($currentAmount - $counterpartyAmount, 2);
            $isCurrentSender = $currentLeg->legRole?->name === 'SENDER';

            $rows->push([
                'transaction_id' => $transaction->id,
                'hfm_account' => $accountName,
                'trading_partner' => $tradingPartner,
                'description' => $transaction->template?->description,
                'adjustment_amount' => $isCurrentSender ? (float) ($currentLeg->adjustment_amount ?? 0) : null,
                'final_amount' => $isCurrentSender ? $currentLeg->final_amount : null,
                'category_label' => $categoryLabel,
                'current_amount' => $currentAmount,
                'counterparty_amount' => $counterpartyAmount,
                'variance' => $rowVariance,
                'current_nature' => $nature,
                'counterparty_nature' => $otherLeg?->legNature?->name,
                'agreement' => [
                    'id' => $receiverLeg?->agreement_status_id,
                    'name' => $agreementName,
                    'label' => $agreementLabel,
                ],
                'prepared_by' => $currentLeg->preparedBy?->name ?: null,
                'prepared_at' => $currentLeg->prepared_at ? $currentLeg->prepared_at->toIso8601String() : null,
                'reviewed_by' => $currentLeg->reviewedBy?->name ?: null,
                'reviewed_at' => $currentLeg->reviewed_at ? $currentLeg->reviewed_at->toIso8601String() : null,
                'counter_prepared_by' => $otherLeg?->preparedBy?->name ?: null,
                'counter_prepared_at' => $otherLeg?->prepared_at ? $otherLeg->prepared_at->toIso8601String() : null,
                'counter_reviewed_by' => $otherLeg?->reviewedBy?->name ?: null,
                'counter_reviewed_at' => $otherLeg?->reviewed_at ? $otherLeg->reviewed_at->toIso8601String() : null,
            ]);

            $this->accumulateTotals($totals, $statementName, $nature, $amount);
            $totals['current_total'] += $currentAmount;
            $totals['counterparty_total'] += $counterpartyAmount;
        }

        $rows = $rows->sortBy(fn ($row) => strtolower($row['trading_partner'] ?? ''))->values();

        $totals['transactions'] = $rows->count();

        $response = [
            'statement' => [
                'id' => $statement->id,
                'name' => $statement->name,
                'label' => $statement->display_label,
            ],
            'period' => [
                'id' => $period->id,
                'label' => $period->label,
                'period_number' => $period->period_number,
                'year' => $period->year,
                'month' => $period->month,
            ],
            'current_company' => [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
                'code' => $currentCompany->code,
            ],
            'meta' => [
                'is_admin' => $isAdmin,
                'companies' => $isAdmin ? $this->companiesList() : [],
            ],
            'totals' => $this->formatTotals($totals, $statementName),
            'rows' => $rows,
        ];

        return response()->json($response);
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

    protected function companiesList(): Collection
    {
        return Company::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ]);
    }

    protected function accumulateTotals(array &$totals, string $statementName, ?string $nature, float $amount): void
    {
        if ($statementName === 'BALANCE_SHEET') {
            if ($nature === 'RECEIVABLE') {
                $totals['receivable'] += $amount;
            } elseif ($nature === 'PAYABLE') {
                $totals['payable'] += $amount;
            }

            return;
        }

        if ($statementName === 'INCOME_STATEMENT') {
            if ($nature === 'REVENUE') {
                $totals['revenue'] += $amount;
            } elseif ($nature === 'EXPENSE') {
                $totals['expense'] += $amount;
            }
        }
    }

    protected function formatTotals(array $totals, string $statementName): array
    {
        $confirmationsVariance = round($totals['current_total'] - $totals['counterparty_total'], 2);

        if ($statementName === 'BALANCE_SHEET') {
            return [
                'receivable' => round($totals['receivable'], 2),
                'payable' => round($totals['payable'], 2),
                'net_variance' => round($totals['receivable'] - $totals['payable'], 2),
                'current_total' => round($totals['current_total'], 2),
                'counterparty_total' => round($totals['counterparty_total'], 2),
                'confirmations_variance' => $confirmationsVariance,
                'transactions' => $totals['transactions'] ?? 0,
            ];
        }

        return [
            'revenue' => round($totals['revenue'], 2),
            'expense' => round($totals['expense'], 2),
            'net_variance' => round($totals['revenue'] - $totals['expense'], 2),
            'current_total' => round($totals['current_total'], 2),
            'counterparty_total' => round($totals['counterparty_total'], 2),
            'confirmations_variance' => $confirmationsVariance,
            'transactions' => $totals['transactions'] ?? 0,
        ];
    }

    public function export(ReportIndexRequest $request): BinaryFileResponse
    {
        $jsonResponse = $this->__invoke($request);
        $data = $jsonResponse->getData(true);

        if (! empty($data['requires_company'])) {
            abort(422, 'Select a company to export.');
        }

        $rows = collect($data['rows'] ?? [])->map(function ($row) {
            $currentNature = $row['current_nature'] ?? null;
            $counterNature = $row['counterparty_nature'] ?? null;
            $currentAmount = $row['current_amount'] ?? 0;
            $counterAmount = $row['counterparty_amount'] ?? 0;
            if (($data['statement']['name'] ?? '') === 'INCOME_STATEMENT') {
                if ($currentNature === 'REVENUE') {
                    $currentAmount = -abs($currentAmount);
                }
                if ($counterNature === 'REVENUE') {
                    $counterAmount = -abs($counterAmount);
                }
            }

            return [
                'hfm_account' => $row['hfm_account'] ?? '—',
                'trading_partner' => $row['trading_partner'] ?? '—',
                'description' => $row['description'] ?? '—',
                'adjustment_amount' => $row['adjustment_amount'] ?? null,
                'final_amount' => $row['final_amount'] ?? null,
                'current_amount' => $currentAmount,
                'counterparty_amount' => $counterAmount,
                'variance' => $row['variance'] ?? 0,
                'agreement' => $row['agreement']['label'] ?? '—',
                'prepared_by' => $row['prepared_by'] ?? '—',
                'prepared_at' => $row['prepared_at'] ? \Carbon\Carbon::parse($row['prepared_at'])->format('Y-m-d H:i') : '—',
                'reviewed_by' => $row['reviewed_by'] ?? '—',
                'reviewed_at' => $row['reviewed_at'] ? \Carbon\Carbon::parse($row['reviewed_at'])->format('Y-m-d H:i') : '—',
                'counter_prepared_by' => $row['counter_prepared_by'] ?? '—',
                'counter_prepared_at' => $row['counter_prepared_at'] ? \Carbon\Carbon::parse($row['counter_prepared_at'])->format('Y-m-d H:i') : '—',
                'counter_reviewed_by' => $row['counter_reviewed_by'] ?? '—',
                'counter_reviewed_at' => $row['counter_reviewed_at'] ? \Carbon\Carbon::parse($row['counter_reviewed_at'])->format('Y-m-d H:i') : '—',
            ];
        });

        $periodLabel = $data['period']['label'] ?? 'period';
        $companyName = $data['current_company']['name'] ?? 'Company';
        $reportTitle = $data['statement']['label'] ?? 'Report';

        $export = new ConfirmationReportExport(
            $rows,
            $reportTitle,
            $companyName,
            $periodLabel,
            now()
        );

        $filename = sprintf(
            '%s-%s-%s.xlsx',
            strtolower(str_replace(' ', '-', $reportTitle)),
            str_replace(' ', '-', $companyName),
            $periodLabel
        );

        return Excel::download($export, $filename);
    }

    public function exportPdf(ReportIndexRequest $request)
    {
        $jsonResponse = $this->__invoke($request);
        $data = $jsonResponse->getData(true);

        if (! empty($data['requires_company'])) {
            abort(422, 'Select a company to export.');
        }

        $rows = collect($data['rows'] ?? [])->map(function ($row) {
            return [
                'hfm_account' => $row['hfm_account'] ?? '—',
                'trading_partner' => $row['trading_partner'] ?? '—',
                'description' => $row['description'] ?? '—',
                'adjustment_amount' => $row['adjustment_amount'] ?? null,
                'final_amount' => $row['final_amount'] ?? null,
                'current_amount' => $row['current_amount'] ?? 0,
                'counterparty_amount' => $row['counterparty_amount'] ?? 0,
                'variance' => $row['variance'] ?? 0,
                'agreement' => $row['agreement']['label'] ?? '—',
                'prepared_by' => $row['prepared_by'] ?? '—',
                'prepared_at' => $row['prepared_at'] ? \Carbon\Carbon::parse($row['prepared_at'])->format('Y-m-d H:i') : '—',
                'reviewed_by' => $row['reviewed_by'] ?? '—',
                'reviewed_at' => $row['reviewed_at'] ? \Carbon\Carbon::parse($row['reviewed_at'])->format('Y-m-d H:i') : '—',
                'counter_prepared_by' => $row['counter_prepared_by'] ?? '—',
                'counter_prepared_at' => $row['counter_prepared_at'] ? \Carbon\Carbon::parse($row['counter_prepared_at'])->format('Y-m-d H:i') : '—',
                'counter_reviewed_by' => $row['counter_reviewed_by'] ?? '—',
                'counter_reviewed_at' => $row['counter_reviewed_at'] ? \Carbon\Carbon::parse($row['counter_reviewed_at'])->format('Y-m-d H:i') : '—',
            ];
        });

        $periodLabel = $data['period']['label'] ?? 'period';
        $companyName = $data['current_company']['name'] ?? 'Company';
        $reportTitle = $data['statement']['label'] ?? 'Report';

        $pdf = Pdf::loadView('reports.confirmation', [
            'rows' => $rows,
            'companyName' => $companyName,
            'period' => $periodLabel,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'reportTitle' => $reportTitle,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            '%s-%s-%s.pdf',
            strtolower(str_replace(' ', '-', $reportTitle)),
            str_replace(' ', '-', $companyName),
            $periodLabel
        );

        return $pdf->download($filename);
    }
}
