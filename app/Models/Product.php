<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['business_id', 'name', 'stock_kg', 'buy_price', 'sell_price'];

    protected $casts = [
        'stock_kg' => 'decimal:3',
        'buy_price' => 'integer',
        'sell_price' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
