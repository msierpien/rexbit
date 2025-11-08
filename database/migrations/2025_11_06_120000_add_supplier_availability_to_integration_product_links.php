<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        Schema::table('integration_product_links', function (Blueprint $table) use ($connection): void {
            if ($connection === 'pgsql') {
                $table->jsonb('supplier_availability')->nullable()->after('metadata');
            } else {
                $table->json('supplier_availability')->nullable()->after('metadata');
            }
        });

        if ($connection === 'pgsql') {
            DB::statement('CREATE INDEX integration_product_links_supplier_availability_idx ON integration_product_links USING GIN (supplier_availability)');
        } else {
            Schema::table('integration_product_links', function (Blueprint $table): void {
                $table->index('supplier_availability', 'integration_product_links_supplier_availability_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS integration_product_links_supplier_availability_idx');
        } else {
            Schema::table('integration_product_links', function (Blueprint $table): void {
                $table->dropIndex('integration_product_links_supplier_availability_idx');
            });
        }

        Schema::table('integration_product_links', function (Blueprint $table): void {
            $table->dropColumn('supplier_availability');
        });
    }
};
