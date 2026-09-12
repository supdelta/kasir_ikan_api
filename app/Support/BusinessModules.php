<?php

namespace App\Support;

/**
 * Katalog modul Delta POS + default per jenis usaha.
 * Dipakai untuk menampilkan/menyembunyikan menu di aplikasi sesuai jenis usaha.
 */
class BusinessModules
{
    /** Semua modul yang tersedia: key => label. */
    public const CATALOG = [
        'timbang_kg'   => 'Jual per Kg (timbang)',
        'piutang'      => 'Piutang',
        'hutang'       => 'Hutang',
        'kas'          => 'Kas & Saldo',
        'laporan'      => 'Laporan',
        'marketplace'  => 'Toko Online (Marketplace)',
        'multi_cabang' => 'Multi Cabang',
    ];

    /** Jenis usaha: key => label. */
    public const TYPES = [
        'ikan'          => 'Toko Ikan (timbang/kg)',
        'retail'        => 'Retail Umum (satuan/pcs)',
        'retail_online' => 'Retail + Online (marketplace)',
    ];

    /** Modul default menurut jenis usaha. */
    public static function defaultsFor(?string $type): array
    {
        return match ($type) {
            'retail' => ['piutang', 'hutang', 'kas', 'laporan'],
            'retail_online' => ['piutang', 'hutang', 'kas', 'laporan', 'marketplace'],
            default => ['timbang_kg', 'piutang', 'hutang', 'kas', 'laporan'], // ikan
        };
    }

    /** Bersihkan daftar modul agar hanya key valid. */
    public static function sanitize(array $modules): array
    {
        return array_values(array_intersect(array_keys(self::CATALOG), $modules));
    }
}
