<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_label',
    ];

    public function accountCategories(): HasMany
    {
        return $this->hasMany(AccountCategory::class);
    }

    public function hfmAccountPairs(): HasMany
    {
        return $this->hasMany(HfmAccountPair::class);
    }

    public function legNatures(): HasMany
    {
        return $this->hasMany(LegNature::class);
    }
}
