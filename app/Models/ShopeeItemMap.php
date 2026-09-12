<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeItemMap extends Model
{
    protected $fillable = [
        'business_id', 'product_id', 'shopee_item_id', 'shopee_model_id', 'sku', 'kg_per_unit',
    ];

    protected $casts = [
        'shopee_item_id' => 'integer',
        'shopee_model_id' => 'integer',
        'kg_per_unit' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
