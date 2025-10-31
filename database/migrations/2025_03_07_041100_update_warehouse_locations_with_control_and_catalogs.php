<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_locations')) {
            Schema::table('warehouse_locations', function (Blueprint $table): void {
                if (! Schema::hasColumn('warehouse_locations', 'strict_control')) {
                    $table->boolean('strict_control')->default(false)->after('is_default');
                }
            });
        }

        if (! Schema::hasTable('product_catalog_warehouse_location')) {
            Schema::create('product_catalog_warehouse_location', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('warehouse_location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_catalog_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['warehouse_location_id', 'product_catalog_id'], 'warehouse_location_catalog_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_catalog_warehouse_location');

        if (Schema::hasTable('warehouse_locations') && Schema::hasColumn('warehouse_locations', 'strict_control')) {
            Schema::table('warehouse_locations', function (Blueprint $table): void {
                $table->dropColumn('strict_control');
            });
        }
    }
};
