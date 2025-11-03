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
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('system_quantity', 10, 3)->default(0);
            $table->decimal('counted_quantity', 10, 3)->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->string('scanned_ean')->nullable();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id']);
            $table->index(['inventory_count_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
};
