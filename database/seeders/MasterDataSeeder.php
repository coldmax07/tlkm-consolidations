<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AgreementStatus;
use App\Models\FinancialStatement;
use App\Models\HfmAccount;
use App\Models\HfmAccountPair;
use App\Models\LegNature;
use App\Models\LegRole;
use App\Models\LegStatus;
use App\Models\MessageRoleContext;
use App\Models\PeriodStatus;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statements = collect([
            ['name' => 'BALANCE_SHEET', 'display_label' => 'Balance Sheet'],
            ['name' => 'INCOME_STATEMENT', 'display_label' => 'Income Statement'],
        ]);

        $statementMap = $statements->mapWithKeys(function (array $data) {
            $statement = FinancialStatement::updateOrCreate(
                ['name' => $data['name']],
                ['display_label' => $data['display_label']]
            );

            return [$data['name'] => $statement];
        });

        $categories = [
            ['statement' => 'BALANCE_SHEET', 'name' => 'RECEIVABLE', 'display_label' => 'Receivable'],
            ['statement' => 'BALANCE_SHEET', 'name' => 'PAYABLE', 'display_label' => 'Payable'],
            ['statement' => 'INCOME_STATEMENT', 'name' => 'REVENUE', 'display_label' => 'Revenue'],
            ['statement' => 'INCOME_STATEMENT', 'name' => 'EXPENSE', 'display_label' => 'Expense'],
        ];

        $categoryMap = collect($categories)->mapWithKeys(function (array $data) use ($statementMap) {
            $category = AccountCategory::updateOrCreate(
                [
                    'name' => $data['name'],
                    'financial_statement_id' => $statementMap[$data['statement']]->id,
                ],
                [
                    'display_label' => $data['display_label'],
                ],
            );

            return [$data['name'] => $category];
        });

        $hfmAccounts = [
            // Balance Sheet
            ['category' => 'RECEIVABLE', 'name' => 'Gross Trade Receivable'],
            ['category' => 'RECEIVABLE', 'name' => 'Other Receivable'],
            ['category' => 'RECEIVABLE', 'name' => 'Loan Receivable'],
            ['category' => 'PAYABLE', 'name' => 'Trade Payable'],
            ['category' => 'PAYABLE', 'name' => 'Other Payable'],
            ['category' => 'PAYABLE', 'name' => 'Loan Payable'],
            // Income Statement
            ['category' => 'REVENUE', 'name' => 'Operating Revenue'],
            ['category' => 'REVENUE', 'name' => 'Sundry Revenue'],
            ['category' => 'REVENUE', 'name' => 'Dividend Income'],
            ['category' => 'REVENUE', 'name' => 'Interest Income'],
            ['category' => 'EXPENSE', 'name' => 'Maintenance Expense'],
            ['category' => 'EXPENSE', 'name' => 'Other Expense'],
            ['category' => 'EXPENSE', 'name' => 'Dividend Paid'],
            ['category' => 'EXPENSE', 'name' => 'Interest Expense'],
        ];

        $accountMap = collect($hfmAccounts)->mapWithKeys(function (array $account) use ($categoryMap) {
            $model = HfmAccount::updateOrCreate(
                [
                    'name' => $account['name'],
                    'account_category_id' => $categoryMap[$account['category']]->id,
                ],
                [
                    'display_label' => $account['name'],
                ],
            );

            return [$account['name'] => $model];
        });

        $accountPairs = [
            // Balance Sheet rules (Receivable -> Payable)
            ['statement' => 'BALANCE_SHEET', 'sender' => 'Gross Trade Receivable', 'receiver' => 'Trade Payable'],
            ['statement' => 'BALANCE_SHEET', 'sender' => 'Other Receivable', 'receiver' => 'Other Payable'],
            ['statement' => 'BALANCE_SHEET', 'sender' => 'Loan Receivable', 'receiver' => 'Loan Payable'],
            // Income Statement rules (Revenue -> Expense)
            ['statement' => 'INCOME_STATEMENT', 'sender' => 'Operating Revenue', 'receiver' => 'Maintenance Expense'],
            ['statement' => 'INCOME_STATEMENT', 'sender' => 'Sundry Revenue', 'receiver' => 'Other Expense'],
            ['statement' => 'INCOME_STATEMENT', 'sender' => 'Dividend Income', 'receiver' => 'Dividend Paid'],
            ['statement' => 'INCOME_STATEMENT', 'sender' => 'Interest Income', 'receiver' => 'Interest Expense'],
        ];

        foreach ($accountPairs as $pair) {
            HfmAccountPair::updateOrCreate(
                [
                    'financial_statement_id' => $statementMap[$pair['statement']]->id,
                    'sender_hfm_account_id' => $accountMap[$pair['sender']]->id,
                ],
                [
                    'receiver_hfm_account_id' => $accountMap[$pair['receiver']]->id,
                ],
            );
        }

        $periodStatuses = [
            ['name' => 'OPEN', 'display_label' => 'Open'],
            ['name' => 'CLOSED', 'display_label' => 'Closed'],
        ];

        foreach ($periodStatuses as $status) {
            PeriodStatus::updateOrCreate(['name' => $status['name']], ['display_label' => $status['display_label']]);
        }

        $legStatuses = [
            ['name' => 'DRAFT', 'display_label' => 'Draft', 'is_final' => false],
            ['name' => 'PENDING_REVIEW', 'display_label' => 'Pending Review', 'is_final' => false],
            ['name' => 'REVIEWED', 'display_label' => 'Reviewed', 'is_final' => true],
            ['name' => 'REJECTED', 'display_label' => 'Rejected', 'is_final' => true],
        ];

        foreach ($legStatuses as $status) {
            LegStatus::updateOrCreate(
                ['name' => $status['name']],
                ['display_label' => $status['display_label'], 'is_final' => $status['is_final']]
            );
        }

        $legNatures = [
            ['name' => 'RECEIVABLE', 'display_label' => 'Receivable', 'statement' => 'BALANCE_SHEET'],
            ['name' => 'PAYABLE', 'display_label' => 'Payable', 'statement' => 'BALANCE_SHEET'],
            ['name' => 'REVENUE', 'display_label' => 'Revenue', 'statement' => 'INCOME_STATEMENT'],
            ['name' => 'EXPENSE', 'display_label' => 'Expense', 'statement' => 'INCOME_STATEMENT'],
        ];

        foreach ($legNatures as $nature) {
            LegNature::updateOrCreate(
                [
                    'name' => $nature['name'],
                    'financial_statement_id' => $statementMap[$nature['statement']]->id,
                ],
                [
                    'display_label' => $nature['display_label'],
                ],
            );
        }

        $legRoles = [
            ['name' => 'SENDER', 'display_label' => 'Sender'],
            ['name' => 'RECEIVER', 'display_label' => 'Receiver'],
        ];

        foreach ($legRoles as $role) {
            LegRole::updateOrCreate(['name' => $role['name']], ['display_label' => $role['display_label']]);
        }

        $agreementStatuses = [
            ['name' => 'UNKNOWN', 'display_label' => 'Unknown'],
            ['name' => 'AGREE', 'display_label' => 'Agree'],
            ['name' => 'DISAGREE', 'display_label' => 'Disagree'],
        ];

        foreach ($agreementStatuses as $status) {
            AgreementStatus::updateOrCreate(['name' => $status['name']], ['display_label' => $status['display_label']]);
        }

        $messageRoleContexts = [
            ['name' => 'PREPARER', 'display_label' => 'Preparer'],
            ['name' => 'REVIEWER', 'display_label' => 'Reviewer'],
            ['name' => 'ADMIN', 'display_label' => 'Admin'],
        ];

        foreach ($messageRoleContexts as $context) {
            MessageRoleContext::updateOrCreate(
                ['name' => $context['name']],
                ['display_label' => $context['display_label']]
            );
        }
    }
}
