<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Izolacja per użytkownik
            
            // Dane statusu
            $table->string('key')->index(); // np. 'awaiting_payment'
            $table->string('name'); // np. 'Oczekuje płatności'
            $table->string('color')->default('gray'); // kolor w UI
            $table->text('description')->nullable();
            
            // Typ i kategoria
            $table->enum('type', ['order', 'payment']); // czy to status zamówienia czy płatności
            $table->boolean('is_default')->default(false); // czy to domyślny status dla nowych zamówień
            $table->boolean('is_final')->default(false); // czy to status końcowy (completed, cancelled)
            
            // Kolejność wyświetlania
            $table->integer('sort_order')->default(0);
            
            // Konfiguracja
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // czy to status systemowy (nie można usunąć)
            
            $table->timestamps();
            
            // Indeksy
            $table->unique(['user_id', 'key', 'type']); // Unikalny klucz per user i typ
            $table->index(['user_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_statuses');
    }
};