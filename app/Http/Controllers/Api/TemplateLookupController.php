<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\Period;
use App\Models\TransactionTemplate;
use Illuminate\Http\JsonResponse;

class TemplateLookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', TransactionTemplate::class);

        $financialStatements = FinancialStatement::with([
            'accountCategories' => function ($query) {
                $query->orderBy('display_label')
                    ->with(['hfmAccounts' => fn ($accounts) => $accounts->orderBy('name')]);
            },
            'hfmAccountPairs' => function ($query) {
                $query->with([
                    'senderAccount',
                    'receiverAccount',
                ]);
            },
        ])->orderBy('display_label')->get()->map(function (FinancialStatement $statement) {
            return [
                'id' => $statement->id,
                'name' => $statement->name,
                'label' => $statement->display_label,
                'categories' => $statement->accountCategories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'label' => $category->display_label,
                        'accounts' => $category->hfmAccounts->map(function ($account) {
                            return [
                                'id' => $account->id,
                                'name' => $account->name,
                            ];
                        })->values(),
                    ];
                })->values(),
                'account_pairs' => $statement->hfmAccountPairs->map(function ($pair) {
                    return [
                        'id' => $pair->id,
                        'sender_account' => [
                            'id' => $pair->senderAccount->id,
                            'name' => $pair->senderAccount->name,
                        ],
                        'receiver_account' => [
                            'id' => $pair->receiverAccount->id,
                            'name' => $pair->receiverAccount->name,
                        ],
                    ];
                })->values(),
            ];
        })->values();

        $companies = Company::orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ])
            ->values();

        $periods = Period::with(['status', 'fiscalYear'])
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn ($period) => [
                'id' => $period->id,
                'label' => $period->label,
                'period_number' => $period->period_number,
                'year' => $period->year,
                'month' => $period->month,
                'starts_on' => $period->starts_on?->toDateString(),
                'ends_on' => $period->ends_on?->toDateString(),
                'is_locked' => $period->isLocked(),
                'locked_at' => $period->locked_at?->toIso8601String(),
                'status' => [
                    'id' => $period->status_id,
                    'name' => $period->status?->name,
                    'label' => $period->status?->display_label,
                ],
                'fiscal_year' => $period->fiscalYear ? [
                    'id' => $period->fiscal_year_id,
                    'label' => $period->fiscalYear->label,
                    'closed_at' => $period->fiscalYear->closed_at?->toIso8601String(),
                ] : null,
            ])
            ->values();

        return response()->json([
            'financial_statements' => $financialStatements,
            'companies' => $companies,
            'periods' => $periods,
        ]);
    }
}
