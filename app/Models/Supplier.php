<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['business_id', 'name', 'phone', 'address'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
