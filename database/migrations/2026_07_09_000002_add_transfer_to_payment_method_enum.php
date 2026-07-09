<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('tunai', 'qris', 'utang', 'transfer') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('tunai', 'qris', 'utang') NULL");
    }
};
