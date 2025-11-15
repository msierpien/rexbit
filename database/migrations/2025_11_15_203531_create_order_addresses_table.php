<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            
            // Powiązanie (dziedziczą user_id przez order)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // Typ adresu
            $table->enum('type', ['billing', 'shipping', 'pickup']); 
            
            // Dane kontaktowe
            $table->string('name')->nullable(); // Imię i nazwisko
            $table->string('company')->nullable(); // Firma
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Adres
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('PL');
            $table->string('state')->nullable(); // Województwo/stan
            
            // Dane firmowe
            $table->string('vat_id')->nullable(); // NIP
            
            // Punkt odbioru (dla type=pickup)
            $table->string('pickup_point_id')->nullable();
            $table->string('pickup_point_name')->nullable();
            
            // Metadane z PrestaShop
            $table->string('external_address_id')->nullable(); // ID z PrestaShop
            $table->json('prestashop_data')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['order_id', 'type']);
            $table->index('external_address_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
