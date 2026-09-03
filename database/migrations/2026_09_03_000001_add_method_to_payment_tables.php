<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->string('method', 20)->default('tunai')->after('amount');
        });

        Schema::table('payable_payments', function (Blueprint $table) {
            $table->string('method', 20)->default('tunai')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });

        Schema::table('payable_payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
