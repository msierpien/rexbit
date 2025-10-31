<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stock_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('on_hand', 12, 3)->default(0);
            $table->decimal('reserved', 12, 3)->default(0);
            $table->decimal('incoming', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'warehouse_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_totals');
    }
};
