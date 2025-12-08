<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year_id',
        'year',
        'month',
        'period_number',
        'label',
        'starts_on',
        'ends_on',
        'status_id',
        'locked_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'period_number' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'locked_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PeriodStatus::class, 'status_id');
    }

    public function isLocked(): bool
    {
        return ! is_null($this->locked_at);
    }
}
