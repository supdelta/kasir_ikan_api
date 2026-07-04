<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff'); // owner | staff
            $table->boolean('can_view_reports')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        // Backfill: pemilik lama jadi member role owner (boleh lihat laporan)
        $businesses = DB::table('businesses')->select('id', 'user_id')->get();
        foreach ($businesses as $b) {
            DB::table('business_members')->insert([
                'business_id' => $b->id,
                'user_id' => $b->user_id,
                'role' => 'owner',
                'can_view_reports' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_members');
    }
};
