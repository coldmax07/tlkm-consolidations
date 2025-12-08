<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\FiscalYear;
use App\Models\Period;
use App\Models\PeriodStatus;
use App\Models\TransactionTemplate;
use App\Services\TemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TestIntercompanySeeder extends Seeder
{
    /**
     * Populate dummy intercompany templates and transactions across company pairs.
     */
    public function run(): void
    {
        $companies = Company::orderBy('name')->get();

        if ($companies->count() < 2) {
            $this->command?->warn('TestIntercompanySeeder: need at least two companies. Seed CompanySeeder first.');

            return;
        }

        $statements = FinancialStatement::with([
            'hfmAccountPairs.senderAccount',
            'hfmAccountPairs.receiverAccount',
        ])->get();

        $templatesCreated = 0;

        foreach ($statements as $statement) {
            $pairs = $statement->hfmAccountPairs->filter(fn ($pair) => $pair->senderAccount && $pair->receiverAccount);

            if ($pairs->isEmpty()) {
                $this->command?->warn("TestIntercompanySeeder: no account pairs for {$statement->display_label}.");
                continue;
            }

            foreach ($companies as $sender) {
                foreach ($companies as $receiver) {
                    if ($sender->id === $receiver->id) {
                        continue;
                    }

                    foreach ($pairs as $pair) {
                        $template = TransactionTemplate::updateOrCreate(
                            [
                                'financial_statement_id' => $statement->id,
                                'sender_company_id' => $sender->id,
                                'receiver_company_id' => $receiver->id,
                                'sender_hfm_account_id' => $pair->sender_hfm_account_id,
                                'receiver_hfm_account_id' => $pair->receiver_hfm_account_id,
                            ],
                            [
                                'sender_account_category_id' => $pair->senderAccount->account_category_id,
                                'receiver_account_category_id' => $pair->receiverAccount->account_category_id,
                                'description' => $this->buildDescription($statement->display_label, $sender->code, $receiver->code, $pair->senderAccount->name, $pair->receiverAccount->name),
                                'currency' => 'ZAR',
                                'default_amount' => $this->randomAmount(),
                                'is_active' => true,
                            ]
                        );

                        if ($template->wasRecentlyCreated) {
                            $templatesCreated++;
                        }
                    }
                }
            }
        }

        $periods = $this->resolveTargetPeriods();

        if ($periods->isEmpty()) {
            $this->command?->warn('TestIntercompanySeeder: no matching periods found for current or prior month.');
            $this->command?->warn('Run FiscalCalendarSeeder or ensure periods exist before generating transactions.');

            return;
        }

        /** @var TemplateService $service */
        $service = app(TemplateService::class);

        foreach ($periods as $period) {
            $created = $service->generateTransactionsForPeriod($period);
            $this->command?->info("Generated {$created} transactions for period {$period->label}.");
        }

        $this->command?->info("Templates processed/created: {$templatesCreated}");
    }

    protected function buildDescription(string $statementLabel, string $senderCode, string $receiverCode, string $senderAccount, string $receiverAccount): string
    {
        return "{$statementLabel}: {$senderCode} -> {$receiverCode} ({$senderAccount} vs {$receiverAccount})";
    }

    /**
     * Use the active fiscal year and open period as anchors, plus the immediately prior period if it exists.
     *
     * @return Collection<int, Period>
     */
    protected function resolveTargetPeriods(): Collection
    {
        $activeFiscalYear = FiscalYear::whereNull('closed_at')
            ->orderByDesc('starts_on')
            ->first();

        if (! $activeFiscalYear) {
            return collect();
        }

        $openStatusId = PeriodStatus::where('name', 'OPEN')->value('id');

        $openPeriods = Period::query()
            ->where('fiscal_year_id', $activeFiscalYear->id)
            ->when($openStatusId, fn ($query) => $query->where('status_id', $openStatusId))
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $anchor = $openPeriods->last();

        if (! $anchor) {
            $anchor = Period::query()
                ->where('fiscal_year_id', $activeFiscalYear->id)
                ->whereNull('locked_at')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();
        }

        if (! $anchor) {
            return collect();
        }

        $previous = Period::query()
            ->where('fiscal_year_id', $activeFiscalYear->id)
            ->where(function ($query) use ($anchor) {
                $query->where('year', '<', $anchor->year)
                    ->orWhere(function ($q) use ($anchor) {
                        $q->where('year', $anchor->year)->where('month', '<', $anchor->month);
                    });
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return collect([$anchor, $previous])->filter()->unique('id')->values();
    }

    protected function randomAmount(): float
    {
        return random_int(1_000_000, 2_000_000_000);
    }
}
