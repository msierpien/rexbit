<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->boolean('is_paid')->default(false)->after('payment_method');

            $table->string('shipping_method')->nullable()->after('shipping_cost');
            $table->json('shipping_details')->nullable()->after('shipping_method');

            $table->json('invoice_data')->nullable()->after('customer_phone');
            $table->boolean('is_company')->default(false)->after('invoice_data');

            $table->index(['user_id', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_paid']);
            $table->dropColumn(['payment_method','is_paid','shipping_method','shipping_details','invoice_data','is_company']);
        });
    }
};
