<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'financial_statement_id' => ['required', 'exists:financial_statements,id'],
            'period_id' => ['required', 'exists:periods,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'counterparty_company_id' => ['nullable', 'exists:companies,id'],
            'sender_company_id' => ['nullable', 'exists:companies,id'],
            'receiver_company_id' => ['nullable', 'exists:companies,id'],
            'status_id' => ['nullable', 'exists:leg_statuses,id'],
            'agreement_status_id' => ['nullable', 'exists:agreement_statuses,id'],
            'hfm_account_id' => ['nullable', 'exists:hfm_accounts,id'],
            'account_category_id' => ['nullable', 'exists:account_categories,id'],
            'leg_role_id' => ['nullable', 'exists:leg_roles,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
            'sort_by' => ['sometimes', 'string', Rule::in(['sender_company', 'receiver_company', 'variance', 'currency'])],
            'sort_dir' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
