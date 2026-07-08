<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Harga beli per kg saat transaksi jual terjadi — untuk HPP akurat
            $table->unsignedBigInteger('buy_price_snapshot')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('buy_price_snapshot');
        });
    }
};
