<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // Powiązania z użytkownikiem i integracją (SECURITY!)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            
            // Podstawowe informacje o zamówieniu
            $table->string('number')->unique(); // Numer zamówienia (generowany)
            $table->string('external_order_id')->nullable(); // ID z PrestaShop
            $table->string('external_reference')->nullable(); // reference z PrestaShop
            
            // Statusy
            $table->enum('status', [
                'draft',
                'awaiting_payment', 
                'paid',
                'awaiting_fulfillment',
                'picking',
                'ready_for_shipment',
                'shipped',
                'completed',
                'cancelled',
                'returned'
            ])->default('draft');
            
            $table->enum('payment_status', [
                'pending',
                'partially_paid',
                'paid', 
                'refunded'
            ])->default('pending');
            
            $table->enum('fulfillment_status', [
                'unassigned',
                'reserved', 
                'picking',
                'packed',
                'shipped'
            ])->default('unassigned');
            
            // Informacje o kliencie (snapshot z PrestaShop)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Finansowe
            $table->string('currency', 3)->default('PLN');
            $table->decimal('total_net', 15, 4)->default(0);
            $table->decimal('total_gross', 15, 4)->default(0);
            $table->decimal('total_paid', 15, 4)->default(0);
            $table->decimal('shipping_cost', 10, 4)->default(0);
            $table->decimal('discount_total', 10, 4)->default(0);
            
            // Metadane z PrestaShop (JSON)
            $table->json('prestashop_data')->nullable(); // Oryginalne dane z PS
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Dodatkowe dane
            
            // Timestampy
            $table->timestamp('order_date')->nullable(); // Data złożenia zamówienia
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indeksy dla wydajności i bezpieczeństwa
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'integration_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['external_order_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
