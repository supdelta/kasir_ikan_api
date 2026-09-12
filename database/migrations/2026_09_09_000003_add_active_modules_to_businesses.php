<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Daftar modul aktif per usaha (mis. ["timbang_kg","piutang","marketplace"]).
            // null = pakai default sesuai category.
            $table->json('active_modules')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('active_modules');
        });
    }
};
