<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayablePayment extends Model
{
    protected $fillable = ['payable_id', 'amount', 'note'];

    protected $casts = ['amount' => 'integer'];

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
