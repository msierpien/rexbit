<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'inventory', 'product', 'order', etc.
            $table->string('direction'); // 'local_to_presta', 'presta_to_local'
            $table->string('status'); // 'pending', 'running', 'completed', 'failed'
            $table->integer('total_items')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->json('metadata')->nullable(); // szczegóły, błędy, itp.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['integration_id', 'type', 'created_at']);
            $table->index('status');
        });

        Schema::create('integration_sync_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('integration_sync_logs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('status'); // 'success', 'failed', 'skipped'
            $table->decimal('quantity', 10, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['sync_log_id', 'status']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_log_items');
        Schema::dropIfExists('integration_sync_logs');
    }
};
