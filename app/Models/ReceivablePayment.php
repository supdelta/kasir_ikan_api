<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceivablePayment extends Model
{
    use SoftDeletes;

    protected $fillable = ['receivable_id', 'amount', 'paid_at'];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
