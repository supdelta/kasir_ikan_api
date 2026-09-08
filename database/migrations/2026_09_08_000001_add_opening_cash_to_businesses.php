<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo awal kas per usaha. Saldo kas ditampilkan =
     * opening_cash_* + gerakan sejak opening_cash_date.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->bigInteger('opening_cash_tunai')->default(0)->after('enforce_stock_limit');
            $table->bigInteger('opening_cash_bank')->default(0)->after('opening_cash_tunai');
            $table->date('opening_cash_date')->nullable()->after('opening_cash_bank');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['opening_cash_tunai', 'opening_cash_bank', 'opening_cash_date']);
        });
    }
};
