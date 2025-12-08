<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_statement_id',
        'sender_company_id',
        'receiver_company_id',
        'sender_account_category_id',
        'sender_hfm_account_id',
        'receiver_account_category_id',
        'receiver_hfm_account_id',
        'description',
        'currency',
        'default_amount',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_amount' => 'decimal:2',
    ];

    public function financialStatement(): BelongsTo
    {
        return $this->belongsTo(FinancialStatement::class);
    }

    public function senderCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'sender_company_id');
    }

    public function receiverCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'receiver_company_id');
    }

    public function senderCategory(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'sender_account_category_id');
    }

    public function receiverCategory(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'receiver_account_category_id');
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(HfmAccount::class, 'sender_hfm_account_id');
    }

    public function receiverAccount(): BelongsTo
    {
        return $this->belongsTo(HfmAccount::class, 'receiver_hfm_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(IcTransaction::class, 'transaction_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
