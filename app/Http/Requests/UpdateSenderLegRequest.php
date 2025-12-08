<?php

namespace App\Http\Requests;

use App\Models\IcTransactionLeg;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSenderLegRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IcTransactionLeg|null $leg */
        $leg = $this->route('leg');

        return $leg && $this->user()?->can('updateSender', $leg);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
        ];
    }
}
