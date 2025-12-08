<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Period
 */
class PeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'year' => $this->year,
            'month' => $this->month,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => [
                'id' => $this->status_id,
                'name' => $this->status?->name,
                'label' => $this->status?->display_label,
            ],
            'is_locked' => $this->isLocked(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'fiscal_year' => $this->whenLoaded('fiscalYear', function () {
                return [
                    'id' => $this->fiscal_year_id,
                    'label' => $this->fiscalYear->label,
                    'closed_at' => $this->fiscalYear->closed_at?->toIso8601String(),
                ];
            }),
        ];
    }
}
