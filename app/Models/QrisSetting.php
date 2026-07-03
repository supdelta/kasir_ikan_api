<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrisSetting extends Model
{
    protected $fillable = ['business_id', 'merchant_name', 'image_path'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
