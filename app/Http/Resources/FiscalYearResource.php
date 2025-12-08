<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FiscalYear
 */
class FiscalYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'is_closed' => ! is_null($this->closed_at),
            'periods' => PeriodResource::collection(
                $this->whenLoaded('periods') ?? collect()
            ),
        ];
    }
}
