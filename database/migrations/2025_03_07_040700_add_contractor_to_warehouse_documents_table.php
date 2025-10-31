<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_documents')) {
            return;
        }

        Schema::table('warehouse_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_documents', 'contractor_id')) {
                $table->foreignId('contractor_id')->nullable()->after('warehouse_location_id');
            }
        });

        Schema::table('warehouse_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_documents', 'contractor_id')) {
                $table->foreign('contractor_id')->references('id')->on('contractors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('warehouse_documents')) {
            return;
        }

        Schema::table('warehouse_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_documents', 'contractor_id')) {
                $table->dropForeign(['contractor_id']);
                $table->dropColumn('contractor_id');
            }
        });
    }
};
