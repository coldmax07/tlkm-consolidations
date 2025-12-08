<?php

namespace App\Http\Requests;

use App\Models\IcTransaction;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IcTransaction|null $transaction */
        $transaction = $this->route('transaction');

        if (! $transaction || ! $this->user()) {
            return false;
        }

        if ($this->user()->hasRole('group_admin')) {
            return true;
        }

        return in_array($this->user()->company_id, [
            $transaction->sender_company_id,
            $transaction->receiver_company_id,
        ], true);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'role_context_id' => ['nullable', 'exists:message_role_contexts,id'],
        ];
    }
}
