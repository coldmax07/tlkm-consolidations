<?php

namespace App\Http\Requests;

use App\Models\IcTransactionLeg;
use App\Models\AgreementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReceiverLegRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IcTransactionLeg|null $leg */
        $leg = $this->route('leg');

        return $leg && $this->user()?->can('updateReceiver', $leg);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'agreement_status_id' => ['required', 'exists:agreement_statuses,id'],
            'disagree_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $status = AgreementStatus::find($this->input('agreement_status_id'));
            $reason = $this->input('disagree_reason');

            if (! $status) {
                return;
            }

            if ($status->name === 'UNKNOWN') {
                $validator->errors()->add('agreement_status_id', 'Select agree or disagree before continuing.');
            }

            if ($status->name === 'AGREE' && filled($reason)) {
                $validator->errors()->add('disagree_reason', 'Do not provide a reason when agreeing.');
            }

            if ($status->name === 'DISAGREE' && blank($reason)) {
                $validator->errors()->add('disagree_reason', 'Provide a reason when disagreeing with the counterparty amount.');
            }
        });
    }
}
