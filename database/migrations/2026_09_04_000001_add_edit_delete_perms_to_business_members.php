<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->boolean('can_edit_transactions')->default(false)->after('can_view_transactions');
            $table->boolean('can_delete_records')->default(false)->after('can_edit_transactions');
        });
    }

    public function down(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->dropColumn(['can_edit_transactions', 'can_delete_records']);
        });
    }
};
