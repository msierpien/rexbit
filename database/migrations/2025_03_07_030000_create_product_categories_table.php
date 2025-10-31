<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('product_catalogs')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('depth')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'catalog_id', 'slug']);
            $table->index(['user_id', 'catalog_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
