<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    protected $fillable = ['user_id', 'name', 'category', 'logo', 'enforce_stock_limit'];

    protected $casts = ['enforce_stock_limit' => 'boolean'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function qrisSetting(): HasOne
    {
        return $this->hasOne(QrisSetting::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(\App\Models\Account::class);
    }

    /** Keanggotaan user tertentu (null jika bukan anggota). */
    public function memberFor(int $userId): ?BusinessMember
    {
        return $this->members()->where('user_id', $userId)->first();
    }

    /** Buat akun kas default jika belum ada sama sekali. */
    public function seedDefaultAccounts(): void
    {
        if ($this->accounts()->exists()) return;

        $defaults = [
            ['code' => '1-001', 'name' => 'Kas Tunai',          'group_id' => 1],
            ['code' => '1-002', 'name' => 'Kas Bank',           'group_id' => 1],
            ['code' => '4-001', 'name' => 'Penjualan Ikan',     'group_id' => 4],
            ['code' => '6-001', 'name' => 'Gaji Karyawan',      'group_id' => 6],
            ['code' => '6-002', 'name' => 'Biaya Listrik',      'group_id' => 6],
            ['code' => '6-003', 'name' => 'Biaya Transport',    'group_id' => 6],
            ['code' => '6-004', 'name' => 'Biaya Sewa',         'group_id' => 6],
            ['code' => '6-005', 'name' => 'Biaya Operasional',  'group_id' => 6],
        ];

        foreach ($defaults as $acc) {
            $this->accounts()->create($acc);
        }
    }
}
