<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('business_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
            $table->date('transaction_date')->nullable()->after('synced_at');
            $table->string('transaction_number', 30)->nullable()->after('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['customer_id', 'supplier_id', 'transaction_date', 'transaction_number']);
        });
    }
};
