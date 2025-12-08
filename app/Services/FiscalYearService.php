<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Period;
use App\Models\PeriodStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FiscalYearService
{
    /**
     * @var array<string, int>
     */
    protected array $statusCache = [];

    public function createNextFiscalYear(): FiscalYear
    {
        return DB::transaction(function () {
            $latest = FiscalYear::query()->orderByDesc('starts_on')->lockForUpdate()->first();

            if ($latest && is_null($latest->closed_at)) {
                $this->closeFiscalYear($latest);
                $latest->refresh();
            }

            $startYear = $latest ? $latest->starts_on->year + 1 : $this->resolveDefaultStartYear();
            $fiscalStart = CarbonImmutable::create($startYear, 4, 1);
            $fiscalEnd = $fiscalStart->addYear()->subDay();
            $label = "FY{$startYear}-" . ($startYear + 1);

            $fiscalYear = FiscalYear::create([
                'label' => $label,
                'starts_on' => $fiscalStart->toDateString(),
                'ends_on' => $fiscalEnd->toDateString(),
                'closed_at' => null,
            ]);

            for ($i = 0; $i < 12; $i++) {
                $periodStart = $fiscalStart->addMonths($i);
                $periodEnd = $periodStart->endOfMonth();
                $isFirst = $i === 0;

                Period::create([
                    'fiscal_year_id' => $fiscalYear->id,
                    'year' => $periodStart->year,
                    'month' => $periodStart->month,
                    'label' => sprintf('%d-%02d', $periodStart->year, $periodStart->month),
                    'starts_on' => $periodStart->toDateString(),
                    'ends_on' => $periodEnd->toDateString(),
                    'status_id' => $isFirst ? $this->statusId('OPEN') : $this->statusId('CLOSED'),
                    'locked_at' => $isFirst ? null : now(),
                ]);
            }

            return $fiscalYear->load(['periods' => fn ($query) => $query->orderBy('starts_on')]);
        });
    }

    public function closeFiscalYear(FiscalYear $fiscalYear): FiscalYear
    {
        if ($fiscalYear->closed_at) {
            return $fiscalYear->load(['periods' => fn ($query) => $query->orderBy('starts_on')]);
        }

        return DB::transaction(function () use ($fiscalYear) {
            $timestamp = now();

            Period::where('fiscal_year_id', $fiscalYear->id)->update([
                'status_id' => $this->statusId('CLOSED'),
            ]);

            Period::where('fiscal_year_id', $fiscalYear->id)
                ->whereNull('locked_at')
                ->update(['locked_at' => $timestamp]);

            $fiscalYear->update(['closed_at' => $timestamp]);

            return $fiscalYear->fresh(['periods' => fn ($query) => $query->orderBy('starts_on')]);
        });
    }

    public function lockPeriod(Period $period): Period
    {
        if ($period->isLocked()) {
            return $period->fresh(['fiscalYear', 'status']);
        }

        $period->update([
            'status_id' => $this->statusId('CLOSED'),
            'locked_at' => now(),
        ]);

        return $period->fresh(['fiscalYear', 'status']);
    }

    public function unlockPeriod(Period $period): Period
    {
        $period->loadMissing('fiscalYear');

        if ($period->fiscalYear?->closed_at) {
            throw new InvalidArgumentException('Cannot unlock a period in a closed fiscal year.');
        }

        if (! $period->isLocked()) {
            return $period->fresh(['fiscalYear', 'status']);
        }

        return DB::transaction(function () use ($period) {
            Period::where('fiscal_year_id', $period->fiscal_year_id)
                ->where('id', '!=', $period->id)
                ->whereNull('locked_at')
                ->update([
                    'status_id' => $this->statusId('CLOSED'),
                    'locked_at' => now(),
                ]);

            $period->forceFill([
                'status_id' => $this->statusId('OPEN'),
                'locked_at' => null,
            ])->save();

            return $period->fresh(['fiscalYear', 'status']);
        });
    }

    protected function statusId(string $name): int
    {
        if (! isset($this->statusCache[$name])) {
            $id = PeriodStatus::where('name', $name)->value('id');
            if (! $id) {
                throw new InvalidArgumentException("Missing period status: {$name}");
            }
            $this->statusCache[$name] = (int) $id;
        }

        return $this->statusCache[$name];
    }

    protected function resolveDefaultStartYear(): int
    {
        $today = CarbonImmutable::now();

        return $today->month >= 4 ? $today->year : $today->year - 1;
    }
}
