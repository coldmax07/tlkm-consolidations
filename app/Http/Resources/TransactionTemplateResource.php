<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'financial_statement' => [
                'id' => $this->financial_statement_id,
                'name' => $this->financialStatement?->name,
                'label' => $this->financialStatement?->display_label,
            ],
            'sender_company' => [
                'id' => $this->sender_company_id,
                'name' => $this->senderCompany?->name,
                'code' => $this->senderCompany?->code,
            ],
            'receiver_company' => [
                'id' => $this->receiver_company_id,
                'name' => $this->receiverCompany?->name,
                'code' => $this->receiverCompany?->code,
            ],
            'sender_category' => [
                'id' => $this->sender_account_category_id,
                'name' => $this->senderCategory?->name,
                'label' => $this->senderCategory?->display_label,
            ],
            'receiver_category' => [
                'id' => $this->receiver_account_category_id,
                'name' => $this->receiverCategory?->name,
                'label' => $this->receiverCategory?->display_label,
            ],
            'sender_account' => [
                'id' => $this->sender_hfm_account_id,
                'name' => $this->senderAccount?->name,
            ],
            'receiver_account' => [
                'id' => $this->receiver_hfm_account_id,
                'name' => $this->receiverAccount?->name,
            ],
            'description' => $this->description,
            'currency' => $this->currency,
            'default_amount' => $this->default_amount !== null
                ? (float) $this->default_amount
                : null,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
