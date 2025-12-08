<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\TransactionTemplate;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodGenerationController extends Controller
{
    public function __invoke(Request $request, Period $period, TemplateService $service): JsonResponse
    {
        $this->authorize('generate', TransactionTemplate::class);

        $data = $request->validate([
            'financial_statement_id' => ['required', 'exists:financial_statements,id'],
        ]);

        $period->loadMissing('fiscalYear');

        if ($period->fiscalYear?->closed_at) {
            return response()->json([
                'message' => 'This fiscal year is closed. Select an open fiscal year before generating transactions.',
            ], 422);
        }

        if ($period->isLocked()) {
            return response()->json([
                'message' => 'This period is locked. Unlock it before generating transactions.',
            ], 422);
        }

        $created = $service->generateTransactionsForPeriod($period, (int) $data['financial_statement_id']);

        return response()->json([
            'created' => $created,
        ]);
    }
}
