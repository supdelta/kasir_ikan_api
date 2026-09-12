<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toko Shopee yang terhubung ke suatu usaha (token OAuth per shop).
        Schema::create('shopee_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->string('shop_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'shop_id']);
        });

        // Pemetaan produk POS ↔ item Shopee (untuk sinkron stok).
        Schema::create('shopee_item_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shopee_item_id');
            $table->unsignedBigInteger('shopee_model_id')->nullable(); // varian
            $table->string('sku')->nullable();
            // 1 pcs Shopee = berapa kg di POS (untuk produk timbang). Default 1.
            $table->decimal('kg_per_unit', 12, 3)->default(1);
            $table->timestamps();
            $table->unique(['business_id', 'shopee_item_id', 'shopee_model_id'], 'shopee_map_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_item_maps');
        Schema::dropIfExists('shopee_shops');
    }
};
