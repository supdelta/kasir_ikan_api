<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk transaksi tipe "mutasi" (Pindah Kas / setor tunai ke bank).
     * cash_from & cash_to berisi 'tunai' atau 'bank'. Untuk tipe lain tetap null.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('cash_from', 10)->nullable()->after('payment_method');
            $table->string('cash_to', 10)->nullable()->after('cash_from');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['cash_from', 'cash_to']);
        });
    }
};
