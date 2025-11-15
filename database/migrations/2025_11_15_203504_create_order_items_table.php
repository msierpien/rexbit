<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            // Powiązania (dziedziczą user_id przez order)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_product_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            
            // Snapshot produktu (na moment zamówienia)
            $table->string('name'); // Nazwa produktu
            $table->string('sku')->nullable();
            $table->string('ean')->nullable(); 
            $table->string('external_product_id')->nullable(); // ID z PrestaShop
            $table->string('product_reference')->nullable(); // reference z PS
            
            // Ilości i ceny
            $table->integer('quantity');
            $table->decimal('price_net', 12, 4);
            $table->decimal('price_gross', 12, 4);
            $table->decimal('unit_price_net', 12, 4); // Cena za sztukę
            $table->decimal('unit_price_gross', 12, 4);
            $table->decimal('vat_rate', 5, 2)->default(0); // Stawka VAT w %
            $table->decimal('discount_total', 10, 4)->default(0);
            
            // Dane magazynowe
            $table->decimal('weight', 8, 3)->nullable();
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_shipped')->default(0);
            
            // Metadane z PrestaShop
            $table->json('prestashop_data')->nullable(); // Oryginalne dane z PS order_detail
            $table->json('metadata')->nullable(); // Dodatkowe dane
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['order_id', 'product_id']);
            $table->index('external_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
