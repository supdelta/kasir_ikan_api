<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = ['business_id', 'code', 'name', 'group_id'];

    protected $casts = ['group_id' => 'integer'];

    public static array $groups = [
        1 => 'Aset',
        2 => 'Hutang',
        3 => 'Modal',
        4 => 'Pendapatan',
        5 => 'HPP',
        6 => 'Biaya Umum',
        7 => 'Pendapatan Lain',
        8 => 'Biaya Lain',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getGroupNameAttribute(): string
    {
        return self::$groups[$this->group_id] ?? '-';
    }

    protected $appends = ['group_name'];
}
