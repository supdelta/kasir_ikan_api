<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receivable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'transaction_id', 'customer_name',
        'total', 'remaining', 'is_paid', 'note',
    ];

    protected $casts = [
        'total' => 'integer',
        'remaining' => 'integer',
        'is_paid' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }
}
