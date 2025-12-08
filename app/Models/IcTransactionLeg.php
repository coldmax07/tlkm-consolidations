<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IcTransactionLeg extends Model
{
    use HasFactory;

    protected $fillable = [
        'ic_transaction_id',
        'company_id',
        'counterparty_company_id',
        'leg_role_id',
        'leg_nature_id',
        'hfm_account_id',
        'status_id',
        'prepared_by_id',
        'prepared_at',
        'reviewed_by_id',
        'reviewed_at',
        'amount',
        'agreement_status_id',
        'disagree_reason',
        'counterparty_amount_snapshot',
    ];

    protected $casts = [
        'prepared_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'amount' => 'decimal:2',
        'counterparty_amount_snapshot' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(IcTransaction::class, 'ic_transaction_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function counterpartyCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'counterparty_company_id');
    }

    public function legRole(): BelongsTo
    {
        return $this->belongsTo(LegRole::class);
    }

    public function legNature(): BelongsTo
    {
        return $this->belongsTo(LegNature::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(HfmAccount::class, 'hfm_account_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LegStatus::class, 'status_id');
    }

    public function agreementStatus(): BelongsTo
    {
        return $this->belongsTo(AgreementStatus::class, 'agreement_status_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IcLegStatusHistory::class, 'ic_transaction_leg_id');
    }
}
