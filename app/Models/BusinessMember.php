<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    protected $fillable = ['business_id', 'user_id', 'role', 'can_view_reports', 'can_view_piutang', 'can_view_hutang', 'can_view_transactions'];

    protected $casts = [
        'can_view_reports'       => 'boolean',
        'can_view_piutang'       => 'boolean',
        'can_view_hutang'        => 'boolean',
        'can_view_transactions'  => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
