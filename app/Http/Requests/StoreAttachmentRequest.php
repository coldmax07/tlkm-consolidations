<?php

namespace App\Http\Requests;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Message|null $message */
        $message = $this->route('message');

        if (! $message || ! $this->user()) {
            return false;
        }

        if ($this->user()->hasRole('group_admin')) {
            return true;
        }

        $transaction = $message->thread?->transaction;

        if (! $transaction) {
            return false;
        }

        return in_array($this->user()->company_id, [
            $transaction->sender_company_id,
            $transaction->receiver_company_id,
        ], true);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }
}
