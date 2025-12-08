<?php

namespace App\Services;

use App\Models\AgreementStatus;
use App\Models\IcTransaction;
use App\Models\IcTransactionLeg;
use App\Models\LegNature;
use App\Models\LegRole;
use App\Models\LegStatus;
use App\Models\Period;
use App\Models\TransactionTemplate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TemplateService
{
    /**
     * Generate transactions for the provided period.
     *
     * @return int Number of new transactions created
     */
    public function generateTransactionsForPeriod(Period $period, ?int $financialStatementId = null): int
    {
        $query = TransactionTemplate::query()
            ->with([
                'financialStatement',
                'senderCompany',
                'receiverCompany',
                'senderCategory',
                'receiverCategory',
                'senderAccount',
                'receiverAccount',
            ])
            ->active();

        if ($financialStatementId) {
            $query->where('financial_statement_id', $financialStatementId);
        }

        /** @var EloquentCollection<TransactionTemplate> $templates */
        $templates = $query->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $meta = $this->resolveLegMeta();

        $created = 0;

        DB::transaction(function () use ($templates, $period, $meta, &$created) {
            foreach ($templates as $template) {
                $transaction = IcTransaction::firstOrCreate(
                    [
                        'period_id' => $period->id,
                        'transaction_template_id' => $template->id,
                    ],
                    [
                        'financial_statement_id' => $template->financial_statement_id,
                        'sender_company_id' => $template->sender_company_id,
                        'receiver_company_id' => $template->receiver_company_id,
                        'currency' => $template->currency,
                        'created_from_default_amount' => ! is_null($template->default_amount),
                    ],
                );

                if ($transaction->wasRecentlyCreated) {
                    $this->createLegs($transaction, $template, $meta);
                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * @return array{roles: array<string, int>, natures: array<string, int>, status: int, agreement_unknown: int}
     */
    protected function resolveLegMeta(): array
    {
        $roles = LegRole::whereIn('name', ['SENDER', 'RECEIVER'])
            ->pluck('id', 'name')
            ->all();

        $natures = LegNature::whereIn('name', ['RECEIVABLE', 'PAYABLE', 'REVENUE', 'EXPENSE'])
            ->pluck('id', 'name')
            ->all();

        $statusId = LegStatus::where('name', 'DRAFT')->value('id');
        $agreementUnknown = AgreementStatus::where('name', 'UNKNOWN')->value('id');

        if (! $statusId || count($roles) < 2 || count($natures) < 4) {
            throw new InvalidArgumentException('Missing master data required for transaction generation.');
        }

        return [
            'roles' => $roles,
            'natures' => $natures,
            'status' => $statusId,
            'agreement_unknown' => $agreementUnknown,
        ];
    }

    protected function createLegs(IcTransaction $transaction, TransactionTemplate $template, array $meta): void
    {
        $statementName = $template->financialStatement?->name;
        $mapping = $this->statementLegMapping($statementName);

        if (! $mapping) {
            throw new InvalidArgumentException("Unsupported financial statement: {$statementName}");
        }

        $legs = [
            [
                'company_id' => $template->sender_company_id,
                'counterparty_company_id' => $template->receiver_company_id,
                'leg_role' => 'SENDER',
                'leg_nature' => $mapping['sender'],
                'hfm_account_id' => $template->sender_hfm_account_id,
                'agreement_status_id' => null,
            ],
            [
                'company_id' => $template->receiver_company_id,
                'counterparty_company_id' => $template->sender_company_id,
                'leg_role' => 'RECEIVER',
                'leg_nature' => $mapping['receiver'],
                'hfm_account_id' => $template->receiver_hfm_account_id,
                'agreement_status_id' => Arr::get($meta, 'agreement_unknown'),
            ],
        ];

        foreach ($legs as $leg) {
            IcTransactionLeg::create([
                'ic_transaction_id' => $transaction->id,
                'company_id' => $leg['company_id'],
                'counterparty_company_id' => $leg['counterparty_company_id'],
                'leg_role_id' => Arr::get($meta['roles'], $leg['leg_role']),
                'leg_nature_id' => Arr::get($meta['natures'], $leg['leg_nature']),
                'hfm_account_id' => $leg['hfm_account_id'],
                'status_id' => $meta['status'],
                'amount' => $template->default_amount,
                'agreement_status_id' => $leg['agreement_status_id'],
            ]);
        }
    }

    protected function statementLegMapping(?string $statementName): ?array
    {
        return match ($statementName) {
            'BALANCE_SHEET' => ['sender' => 'RECEIVABLE', 'receiver' => 'PAYABLE'],
            'INCOME_STATEMENT' => ['sender' => 'REVENUE', 'receiver' => 'EXPENSE'],
            default => null,
        };
    }
}
