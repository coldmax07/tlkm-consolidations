<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasFactory;

    protected $fillable = [
        'ic_transaction_id',
        'created_by_id',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(IcTransaction::class, 'ic_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
