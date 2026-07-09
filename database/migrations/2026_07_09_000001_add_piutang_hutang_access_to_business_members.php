<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->boolean('can_view_piutang')->default(false)->after('can_view_reports');
            $table->boolean('can_view_hutang')->default(false)->after('can_view_piutang');
        });

        // Owner selalu punya akses penuh
        DB::table('business_members')->where('role', 'owner')->update([
            'can_view_piutang' => true,
            'can_view_hutang'  => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->dropColumn(['can_view_piutang', 'can_view_hutang']);
        });
    }
};
