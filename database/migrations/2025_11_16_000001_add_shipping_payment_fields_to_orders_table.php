<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Dodaj kolumny tylko jeśli ich nie ma (środowiska już miały część pól)
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
            if (!Schema::hasColumn('orders', 'is_paid')) {
                $table->boolean('is_paid')->default(false);
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipping_details')) {
                $table->json('shipping_details')->nullable();
            }
            if (!Schema::hasColumn('orders', 'invoice_data')) {
                $table->json('invoice_data')->nullable();
            }
            if (!Schema::hasColumn('orders', 'is_company')) {
                $table->boolean('is_company')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'payment_method',
                'is_paid',
                'paid_at',
                'shipping_method',
                'shipping_details',
                'invoice_data',
                'is_company',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
