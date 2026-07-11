<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->boolean('can_view_transactions')->default(false)->after('can_view_hutang');
        });
    }

    public function down(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->dropColumn('can_view_transactions');
        });
    }
};
