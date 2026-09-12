<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeShop extends Model
{
    protected $fillable = [
        'business_id', 'shop_id', 'shop_name',
        'access_token', 'refresh_token', 'token_expires_at',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
