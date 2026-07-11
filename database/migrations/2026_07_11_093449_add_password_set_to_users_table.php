<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_set')->default(true)->after('password');
        });

        // User Google (avatar dari googleusercontent) belum set password sendiri
        DB::statement("UPDATE users SET password_set = 0 WHERE avatar LIKE '%googleusercontent%'");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set');
        });
    }
};
