<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IcTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'transaction_template_id',
        'financial_statement_id',
        'sender_company_id',
        'receiver_company_id',
        'currency',
        'created_from_default_amount',
    ];

    protected $casts = [
        'created_from_default_amount' => 'boolean',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class, 'transaction_template_id');
    }

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

    public function legs(): HasMany
    {
        return $this->hasMany(IcTransactionLeg::class);
    }

    public function thread(): HasOne
    {
        return $this->hasOne(Thread::class, 'ic_transaction_id');
    }
}
