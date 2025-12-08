<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HfmAccountPair extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_statement_id',
        'sender_hfm_account_id',
        'receiver_hfm_account_id',
    ];

    public function financialStatement(): BelongsTo
    {
        return $this->belongsTo(FinancialStatement::class);
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(HfmAccount::class, 'sender_hfm_account_id');
    }

    public function receiverAccount(): BelongsTo
    {
        return $this->belongsTo(HfmAccount::class, 'receiver_hfm_account_id');
    }
}
