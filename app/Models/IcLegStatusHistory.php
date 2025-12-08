<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcLegStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'ic_leg_status_history';

    protected $fillable = [
        'ic_transaction_leg_id',
        'from_status_id',
        'to_status_id',
        'changed_by_id',
        'note',
    ];

    public function leg(): BelongsTo
    {
        return $this->belongsTo(IcTransactionLeg::class, 'ic_transaction_leg_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(LegStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(LegStatus::class, 'to_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
