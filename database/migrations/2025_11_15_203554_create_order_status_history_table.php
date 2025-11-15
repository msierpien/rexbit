<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            
            // Powiązania (dziedziczą user_id przez order)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Zmiana statusu
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('field_name')->default('status'); // status, payment_status, fulfillment_status
            
            // Kontekst zmiany
            $table->text('comment')->nullable(); // Komentarz użytkownika
            $table->enum('source', ['user', 'system', 'integration', 'job'])->default('user');
            $table->string('ip_address')->nullable();
            $table->json('metadata')->nullable(); // Dodatkowe dane kontekstowe
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['order_id', 'created_at']);
            $table->index(['changed_by', 'created_at']);
            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
