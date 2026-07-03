<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['jual', 'beli', 'kas_masuk', 'kas_keluar']);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_kg', 10, 3)->nullable();
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedBigInteger('total');
            $table->enum('payment_method', ['tunai', 'qris', 'utang'])->nullable();
            $table->string('customer_name')->nullable();
            $table->text('note')->nullable();
            $table->uuid('local_uuid')->unique();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index('local_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
