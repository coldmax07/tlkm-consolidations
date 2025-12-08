<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FiscalYearResource;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class FiscalYearController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', FiscalYear::class);

        $years = FiscalYear::with(['periods' => fn ($query) => $query->orderBy('starts_on')])
            ->orderByDesc('starts_on')
            ->get();

        return FiscalYearResource::collection($years);
    }

    public function store(FiscalYearService $service): FiscalYearResource
    {
        $this->authorize('create', FiscalYear::class);

        $fiscalYear = $service->createNextFiscalYear();

        return new FiscalYearResource($fiscalYear);
    }

    public function close(FiscalYear $fiscalYear, FiscalYearService $service): FiscalYearResource|JsonResponse
    {
        $this->authorize('close', $fiscalYear);

        if ($fiscalYear->closed_at) {
            return new FiscalYearResource($fiscalYear->load(['periods' => fn ($query) => $query->orderBy('starts_on')]));
        }

        try {
            $updated = $service->closeFiscalYear($fiscalYear);
        } catch (InvalidArgumentException $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }

        return new FiscalYearResource($updated);
    }
}
