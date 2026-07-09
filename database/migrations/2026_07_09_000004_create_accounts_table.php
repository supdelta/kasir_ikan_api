<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->unsignedTinyInteger('group_id'); // 1-8 sesuai kelompok COA
            $table->timestamps();
            $table->unique(['business_id', 'code']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')
                  ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
        Schema::dropIfExists('accounts');
    }
};
