<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_label',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class, 'status_id');
    }
}
