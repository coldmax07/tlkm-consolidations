<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use App\Models\Period;
use App\Models\PeriodStatus;
use Illuminate\Database\Seeder;
use Carbon\CarbonImmutable;

class FiscalCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $openStatus = PeriodStatus::firstWhere('name', 'OPEN');
        $closedStatus = PeriodStatus::firstWhere('name', 'CLOSED');

        if (! $openStatus || ! $closedStatus) {
            $this->command?->warn('Skipping fiscal calendar seeding. Run MasterDataSeeder first.');

            return;
        }

        $today = CarbonImmutable::now();
        $startYear = $today->month >= 4 ? $today->year : $today->year - 1;
        $fiscalStart = CarbonImmutable::create($startYear, 4, 1);
        $fiscalEnd = $fiscalStart->addYear()->subDay();
        $label = "FY{$startYear}" . '-' . ($startYear + 1);

        $fiscalYear = FiscalYear::updateOrCreate(
            ['label' => $label],
            [
                'starts_on' => $fiscalStart->toDateString(),
                'ends_on' => $fiscalEnd->toDateString(),
                'closed_at' => null,
            ],
        );

        for ($i = 0; $i < 12; $i++) {
            $periodStart = $fiscalStart->addMonths($i);
            $periodEnd = $periodStart->endOfMonth();
            $isFirst = $i === 0;
            $number = $i + 1;

            Period::updateOrCreate(
                ['label' => sprintf('%d-%02d', $periodStart->year, $periodStart->month)],
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'year' => $periodStart->year,
                    'month' => $periodStart->month,
                    'period_number' => $number,
                    'starts_on' => $periodStart->toDateString(),
                    'ends_on' => $periodEnd->toDateString(),
                    'status_id' => $isFirst ? $openStatus->id : $closedStatus->id,
                    'locked_at' => $isFirst ? null : now(),
                ],
            );
        }
    }
}
