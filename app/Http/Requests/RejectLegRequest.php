<?php

namespace App\Http\Requests;

use App\Models\IcTransactionLeg;
use Illuminate\Foundation\Http\FormRequest;

class RejectLegRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IcTransactionLeg|null $leg */
        $leg = $this->route('leg');

        if (! $leg) {
            return false;
        }

        $ability = $leg->legRole?->name === 'RECEIVER' ? 'reviewReceiver' : 'review';

        return $this->user()?->can($ability, $leg);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
