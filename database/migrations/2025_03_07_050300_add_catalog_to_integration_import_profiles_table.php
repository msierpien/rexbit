<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_import_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_import_profiles', 'catalog_id')) {
                $table->foreignId('catalog_id')
                    ->nullable()
                    ->after('integration_id')
                    ->constrained('product_catalogs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('integration_import_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('integration_import_profiles', 'catalog_id')) {
                $table->dropForeign(['catalog_id']);
                $table->dropColumn('catalog_id');
            }
        });
    }
};
