<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_statement_id',
        'name',
        'display_label',
    ];

    public function financialStatement(): BelongsTo
    {
        return $this->belongsTo(FinancialStatement::class);
    }

    public function hfmAccounts(): HasMany
    {
        return $this->hasMany(HfmAccount::class);
    }
}
