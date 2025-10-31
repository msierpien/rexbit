<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_import_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('name');
            $table->string('format', 10); // csv, xml
            $table->string('source_type', 10); // file, url
            $table->text('source_location'); // path or url
            $table->string('delimiter', 5)->nullable();
            $table->boolean('has_header')->default(true);
            $table->boolean('is_active')->default(false);
            $table->string('fetch_mode', 20)->default('manual'); // manual, interval, daily, cron
            $table->unsignedInteger('fetch_interval_minutes')->nullable();
            $table->time('fetch_daily_at')->nullable();
            $table->string('fetch_cron_expression')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->json('last_headers')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'is_active']);
            $table->index(['fetch_mode', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_import_profiles');
    }
};
