<?php

namespace App\Http\Requests;

use App\Models\AccountCategory;
use App\Models\FinancialStatement;
use App\Models\HfmAccount;
use App\Models\HfmAccountPair;
use App\Models\TransactionTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TransactionTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'financial_statement_id' => ['required', 'exists:financial_statements,id'],
            'sender_company_id' => ['required', 'exists:companies,id', 'different:receiver_company_id'],
            'receiver_company_id' => ['required', 'exists:companies,id', 'different:sender_company_id'],
            'sender_account_category_id' => ['required', 'exists:account_categories,id'],
            'sender_hfm_account_id' => ['required', 'exists:hfm_accounts,id'],
            'receiver_account_category_id' => ['required', 'exists:account_categories,id'],
            'receiver_hfm_account_id' => ['required', 'exists:hfm_accounts,id'],
            'description' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('transaction_templates')->where(function ($query) {
                    return $query
                        ->where('financial_statement_id', $this->input('financial_statement_id'))
                        ->where('sender_company_id', $this->input('sender_company_id'))
                        ->where('receiver_company_id', $this->input('receiver_company_id'));
                }),
            ],
            'currency' => ['required', 'string', 'size:3'],
            'default_amount' => ['nullable', 'numeric'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->currency)) {
            $this->merge(['currency' => strtoupper($this->currency)]);
        }
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $statement = $this->loadStatement($this->input('financial_statement_id'));
            if (! $statement) {
                return;
            }

            $this->validateCategoryAlignment($validator, $statement->id);
            $this->validateAccountAlignment($validator);
            $this->validateAccountPairing($validator, $statement->id);
            $this->validateStatementRules($validator, $statement->name);
        });
    }

    protected function validateCategoryAlignment(Validator $validator, int $statementId): void
    {
        $senderCategory = AccountCategory::find($this->input('sender_account_category_id'));
        $receiverCategory = AccountCategory::find($this->input('receiver_account_category_id'));

        if ($senderCategory && $senderCategory->financial_statement_id !== $statementId) {
            $validator->errors()->add('sender_account_category_id', 'Sender category must belong to the selected financial statement.');
        }

        if ($receiverCategory && $receiverCategory->financial_statement_id !== $statementId) {
            $validator->errors()->add('receiver_account_category_id', 'Receiver category must belong to the selected financial statement.');
        }
    }

    protected function validateAccountAlignment(Validator $validator): void
    {
        $senderAccount = HfmAccount::find($this->input('sender_hfm_account_id'));
        $receiverAccount = HfmAccount::find($this->input('receiver_hfm_account_id'));

        if ($senderAccount && $senderAccount->account_category_id !== (int) $this->input('sender_account_category_id')) {
            $validator->errors()->add('sender_hfm_account_id', 'Sender HFM account must belong to the selected category.');
        }

        if ($receiverAccount && $receiverAccount->account_category_id !== (int) $this->input('receiver_account_category_id')) {
            $validator->errors()->add('receiver_hfm_account_id', 'Receiver HFM account must belong to the selected category.');
        }
    }

    protected function validateAccountPairing(Validator $validator, int $statementId): void
    {
        if (! $this->filled(['sender_hfm_account_id', 'receiver_hfm_account_id'])) {
            return;
        }

        $pair = HfmAccountPair::query()
            ->where('financial_statement_id', $statementId)
            ->where('sender_hfm_account_id', $this->input('sender_hfm_account_id'))
            ->where('receiver_hfm_account_id', $this->input('receiver_hfm_account_id'))
            ->first();

        if (! $pair) {
            $message = 'The selected sender and receiver HFM accounts are not a valid pair for this financial statement.';
            $validator->errors()->add('sender_hfm_account_id', $message);
            $validator->errors()->add('receiver_hfm_account_id', $message);
        }
    }

    protected function validateStatementRules(Validator $validator, string $statementName): void
    {
        $senderCategory = AccountCategory::find($this->input('sender_account_category_id'));
        $receiverCategory = AccountCategory::find($this->input('receiver_account_category_id'));

        $requirements = match ($statementName) {
            'BALANCE_SHEET' => ['RECEIVABLE', 'PAYABLE'],
            'INCOME_STATEMENT' => ['REVENUE', 'EXPENSE'],
            default => [null, null],
        };

        if ($requirements[0] && $senderCategory && $senderCategory->name !== $requirements[0]) {
            $validator->errors()->add('sender_account_category_id', "Sender category must be {$requirements[0]} for {$statementName} templates.");
        }

        if ($requirements[1] && $receiverCategory && $receiverCategory->name !== $requirements[1]) {
            $validator->errors()->add('receiver_account_category_id', "Receiver category must be {$requirements[1]} for {$statementName} templates.");
        }
    }

    protected function loadStatement(?int $id): ?FinancialStatement
    {
        return $id ? FinancialStatement::find($id) : null;
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        return Arr::only($data, [
            'financial_statement_id',
            'sender_company_id',
            'receiver_company_id',
            'sender_account_category_id',
            'sender_hfm_account_id',
            'receiver_account_category_id',
            'receiver_hfm_account_id',
            'description',
            'currency',
            'default_amount',
            'is_active',
        ]);
    }
}
