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
        Schema::create('integration_product_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('product_catalogs')->nullOnDelete();
            $table->string('external_product_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('ean')->nullable()->index();
            $table->string('matched_by')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'product_id']);
            $table->index(['integration_id', 'external_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_product_links');
    }
};
