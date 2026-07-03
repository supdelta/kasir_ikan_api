<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('remaining');
            $table->boolean('is_paid')->default(false);
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};
