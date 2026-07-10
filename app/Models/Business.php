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
            // Kelompok 1 - Aset
            ['code' => 'KAS TUNAI',  'name' => 'Kas Tunai',           'group_id' => 1],
            ['code' => 'KASBON',     'name' => 'Kasbon',               'group_id' => 1],
            ['code' => 'PIUTANG',    'name' => 'Piutang Customer',     'group_id' => 1],
            // Kelompok 2 - Hutang
            ['code' => 'HUTANG',     'name' => 'Hutang Supplier',      'group_id' => 2],
            ['code' => 'HUTL',       'name' => 'Hutang Lainnya',       'group_id' => 2],
            // Kelompok 3 - Modal
            ['code' => 'MODAL',      'name' => 'Modal Usaha',          'group_id' => 3],
            // Kelompok 4 - Pendapatan
            ['code' => 'PENJUALAN',  'name' => 'Penjualan',            'group_id' => 4],
            // Kelompok 5 - HPP
            ['code' => 'HPP',        'name' => 'HPP',                  'group_id' => 5],
            // Kelompok 6 - Biaya Umum
            ['code' => 'GAJI',       'name' => 'Gaji Karyawan',        'group_id' => 6],
            ['code' => 'LISTRIK',    'name' => 'Biaya Listrik',        'group_id' => 6],
            ['code' => 'PULSA',      'name' => 'Pulsa',                'group_id' => 6],
            ['code' => 'TRANSPORT',  'name' => 'Biaya Transport',      'group_id' => 6],
            ['code' => 'SEWA',       'name' => 'Biaya Sewa',           'group_id' => 6],
            ['code' => 'ATK',        'name' => 'Biaya ATK',            'group_id' => 6],
            ['code' => 'MAKAN',      'name' => 'Biaya Makan & Minum',  'group_id' => 6],
            ['code' => 'BBM',        'name' => 'BBM',                  'group_id' => 6],
            ['code' => 'PARKIRTOL',  'name' => 'Parkir & Tol',         'group_id' => 6],
            ['code' => 'BY OPS',     'name' => 'Biaya Operasional',    'group_id' => 5],
            ['code' => 'BUM',        'name' => 'Biaya Umum Lainnya',   'group_id' => 6],
        ];

        foreach ($defaults as $acc) {
            $this->accounts()->create($acc);
        }
    }
}
