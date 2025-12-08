<?php

namespace App\Http\Requests;

use App\Models\TransactionTemplate;

class UpdateTransactionTemplateRequest extends StoreTransactionTemplateRequest
{
    public function authorize(): bool
    {
        /** @var TransactionTemplate|null $template */
        $template = $this->route('template');

        return $template && $this->user()?->can('update', $template);
    }
}
