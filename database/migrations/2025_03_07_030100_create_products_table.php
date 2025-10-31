<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('product_catalogs')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('purchase_price_net', 12, 2)->nullable();
            $table->unsignedInteger('purchase_vat_rate')->nullable();
            $table->decimal('sale_price_net', 12, 2)->nullable();
            $table->unsignedInteger('sale_vat_rate')->nullable();
            $table->string('status')->default('draft');
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'catalog_id', 'slug']);
            $table->index(['user_id', 'catalog_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
