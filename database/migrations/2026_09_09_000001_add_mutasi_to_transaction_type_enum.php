<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah 'mutasi' (Pindah Kas) ke enum kolom type di transactions.
     * Pakai raw statement karena mengubah ENUM tidak didukung schema builder biasa.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('jual','beli','kas_masuk','kas_keluar','mutasi') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('jual','beli','kas_masuk','kas_keluar') NOT NULL");
    }
};
