<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\Period;
use App\Services\FiscalYearService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class PeriodLockController extends Controller
{
    public function lock(Period $period, FiscalYearService $service): PeriodResource|JsonResponse
    {
        $this->authorize('lock', $period);

        try {
            $updated = $service->lockPeriod($period);
        } catch (InvalidArgumentException $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }

        return new PeriodResource($updated);
    }

    public function unlock(Period $period, FiscalYearService $service): PeriodResource|JsonResponse
    {
        $this->authorize('unlock', $period);

        try {
            $updated = $service->unlockPeriod($period);
        } catch (InvalidArgumentException $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }

        return new PeriodResource($updated);
    }
}
