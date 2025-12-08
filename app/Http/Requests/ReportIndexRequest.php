<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'statement' => ['required', Rule::in(['BALANCE_SHEET', 'INCOME_STATEMENT'])],
            'company_id' => ['nullable', 'exists:companies,id'],
        ];
    }
}
