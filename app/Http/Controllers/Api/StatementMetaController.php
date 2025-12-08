<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AgreementStatus;
use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\LegStatus;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class StatementMetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $financialStatements = FinancialStatement::orderBy('display_label')
            ->get()
            ->map(fn (FinancialStatement $statement) => [
                'id' => $statement->id,
                'name' => $statement->name,
                'label' => $statement->display_label,
                'slug' => Str::slug(strtolower(str_replace('_', ' ', $statement->name))),
            ]);

        $periods = Period::with(['fiscalYear', 'status'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn (Period $period) => [
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
            ]);

        $companies = Company::orderBy('name')
            ->get(['id', 'name', 'code']);

        $legStatuses = LegStatus::orderBy('display_label')
            ->get(['id', 'name', 'display_label']);

        $agreementStatuses = AgreementStatus::orderBy('display_label')
            ->get(['id', 'name', 'display_label']);

        $categories = AccountCategory::with('hfmAccounts:id,account_category_id,name')
            ->orderBy('display_label')
            ->get()
            ->map(fn (AccountCategory $category) => [
                'id' => $category->id,
                'label' => $category->display_label,
                'name' => $category->name,
                'financial_statement_id' => $category->financial_statement_id,
                'accounts' => $category->hfmAccounts->map(fn ($account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                ]),
            ]);

        return response()->json([
            'financial_statements' => $financialStatements,
            'periods' => $periods,
            'companies' => $companies,
            'leg_statuses' => $legStatuses,
            'agreement_statuses' => $agreementStatuses,
            'account_categories' => $categories,
        ]);
    }
}
