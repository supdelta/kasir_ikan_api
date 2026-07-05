<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'user_id', 'type', 'product_id',
        'quantity_kg', 'unit_price', 'total', 'payment_method',
        'customer_name', 'customer_phone', 'note', 'local_uuid', 'synced_at',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:3',
        'unit_price' => 'integer',
        'total' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class);
    }
}
