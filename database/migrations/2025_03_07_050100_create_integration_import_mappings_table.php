<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_import_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('integration_import_profiles')->cascadeOnDelete();
            $table->string('target_type', 20); // product, category
            $table->string('source_field');
            $table->string('target_field');
            $table->json('transform')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'target_type', 'source_field'], 'profile_target_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_import_mappings');
    }
};
