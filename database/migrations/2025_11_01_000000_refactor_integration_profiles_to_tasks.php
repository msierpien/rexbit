<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename table from integration_import_profiles to integration_tasks
        Schema::rename('integration_import_profiles', 'integration_tasks');

        // Add new columns to integration_tasks
        Schema::table('integration_tasks', function (Blueprint $table) {
            // Add task type (import/export)
            if (!Schema::hasColumn('integration_tasks', 'task_type')) {
                $table->string('task_type', 20)->default('import')->after('name'); // import, export
            }
            
            // Add resource type (products, orders, customers, etc.)
            if (!Schema::hasColumn('integration_tasks', 'resource_type')) {
                $table->string('resource_type', 50)->default('products')->after('task_type');
            }
            
            // Add mappings column (move from separate table)
            if (!Schema::hasColumn('integration_tasks', 'mappings')) {
                $table->json('mappings')->nullable()->after('last_headers');
            }

            // Add filters column
            if (!Schema::hasColumn('integration_tasks', 'filters')) {
                $table->json('filters')->nullable()->after('mappings');
            }
        });

        // Migrate data from integration_import_mappings to integration_tasks.mappings
        if (Schema::hasTable('integration_import_mappings')) {
            DB::statement("
                UPDATE integration_tasks 
                SET mappings = subquery.mappings
                FROM (
                    SELECT 
                        profile_id,
                        json_agg(
                            json_build_object(
                                'source_field', source_field,
                                'target_field', target_field,
                                'target_type', target_type,
                                'transform', transform
                            )
                        ) as mappings
                    FROM integration_import_mappings
                    GROUP BY profile_id
                ) as subquery
                WHERE integration_tasks.id = subquery.profile_id
            ");
        }

        // Update foreign keys in integration_import_runs
        if (Schema::hasTable('integration_import_runs')) {
            Schema::table('integration_import_runs', function (Blueprint $table) {
                $table->dropForeign(['profile_id']);
            });
            
            Schema::rename('integration_import_runs', 'integration_task_runs');
            
            Schema::table('integration_task_runs', function (Blueprint $table) {
                $table->renameColumn('profile_id', 'task_id');
            });
            
            Schema::table('integration_task_runs', function (Blueprint $table) {
                $table->foreign('task_id')->references('id')->on('integration_tasks')->cascadeOnDelete();
            });
        }

        // Drop integration_import_mappings table (data already migrated)
        Schema::dropIfExists('integration_import_mappings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate integration_import_mappings
        Schema::create('integration_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('integration_tasks')->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->string('source_field');
            $table->string('target_field');
            $table->json('transform')->nullable();
            $table->timestamps();
        });

        // Migrate mappings back to separate table (PostgreSQL compatible)
        if (DB::table('integration_tasks')->whereNotNull('mappings')->exists()) {
            DB::statement("
                INSERT INTO integration_import_mappings (profile_id, target_type, source_field, target_field, transform, created_at, updated_at)
                SELECT 
                    it.id as profile_id,
                    COALESCE(m->>'target_type', 'product') as target_type,
                    m->>'source_field' as source_field,
                    m->>'target_field' as target_field,
                    (m->'transform')::json as transform,
                    NOW(),
                    NOW()
                FROM integration_tasks it
                CROSS JOIN json_array_elements(it.mappings) as m
                WHERE it.mappings IS NOT NULL AND it.mappings != 'null'::jsonb
            ");
        }

        // Rename integration_task_runs back
        if (Schema::hasTable('integration_task_runs')) {
            Schema::table('integration_task_runs', function (Blueprint $table) {
                $table->dropForeign(['task_id']);
            });
            
            Schema::table('integration_task_runs', function (Blueprint $table) {
                $table->renameColumn('task_id', 'profile_id');
            });
            
            Schema::rename('integration_task_runs', 'integration_import_runs');
            
            Schema::table('integration_import_runs', function (Blueprint $table) {
                $table->foreign('profile_id')->references('id')->on('integration_tasks')->cascadeOnDelete();
            });
        }

        // Remove new columns
        Schema::table('integration_tasks', function (Blueprint $table) {
            $table->dropColumn(['task_type', 'resource_type', 'mappings', 'filters']);
        });

        // Rename table back
        Schema::rename('integration_tasks', 'integration_import_profiles');
    }
};
