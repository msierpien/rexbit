<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OrderStatus;
use App\Models\User;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        // Dodaj domyślne statusy dla każdego użytkownika
        $users = User::all();
        
        $defaultOrderStatuses = [
            ['key' => 'draft', 'name' => 'Szkic', 'color' => 'gray', 'is_system' => true, 'sort_order' => 10],
            ['key' => 'awaiting_payment', 'name' => 'Oczekuje płatności', 'color' => 'yellow', 'is_default' => true, 'is_system' => true, 'sort_order' => 20],
            ['key' => 'paid', 'name' => 'Opłacone', 'color' => 'green', 'is_system' => true, 'sort_order' => 30],
            ['key' => 'awaiting_fulfillment', 'name' => 'Oczekuje realizacji', 'color' => 'blue', 'is_system' => true, 'sort_order' => 40],
            ['key' => 'shipped', 'name' => 'Wysłane', 'color' => 'purple', 'is_system' => true, 'sort_order' => 50],
            ['key' => 'completed', 'name' => 'Zakończone', 'color' => 'green', 'is_final' => true, 'is_system' => true, 'sort_order' => 60],
            ['key' => 'cancelled', 'name' => 'Anulowane', 'color' => 'red', 'is_final' => true, 'is_system' => true, 'sort_order' => 70],
            ['key' => 'refunded', 'name' => 'Zwrócone', 'color' => 'orange', 'is_final' => true, 'is_system' => true, 'sort_order' => 80],
            ['key' => 'payment_error', 'name' => 'Błąd płatności', 'color' => 'red', 'is_system' => true, 'sort_order' => 90],
        ];

        $defaultPaymentStatuses = [
            ['key' => 'pending', 'name' => 'Oczekująca', 'color' => 'yellow', 'is_default' => true, 'is_system' => true, 'sort_order' => 10],
            ['key' => 'paid', 'name' => 'Opłacona', 'color' => 'green', 'is_system' => true, 'sort_order' => 20],
            ['key' => 'partially_paid', 'name' => 'Częściowo opłacona', 'color' => 'blue', 'is_system' => true, 'sort_order' => 30],
            ['key' => 'refunded', 'name' => 'Zwrócona', 'color' => 'orange', 'is_final' => true, 'is_system' => true, 'sort_order' => 40],
            ['key' => 'partially_refunded', 'name' => 'Częściowo zwrócona', 'color' => 'orange', 'is_system' => true, 'sort_order' => 50],
            ['key' => 'payment_error', 'name' => 'Błąd płatności', 'color' => 'red', 'is_system' => true, 'sort_order' => 60],
        ];

        foreach ($users as $user) {
            // Dodaj statusy zamówień
            foreach ($defaultOrderStatuses as $status) {
                OrderStatus::firstOrCreate([
                    'user_id' => $user->id,
                    'key' => $status['key'],
                    'type' => 'order',
                ], array_merge($status, [
                    'type' => 'order',
                    'user_id' => $user->id,
                ]));
            }

            // Dodaj statusy płatności
            foreach ($defaultPaymentStatuses as $status) {
                OrderStatus::firstOrCreate([
                    'user_id' => $user->id,
                    'key' => $status['key'],
                    'type' => 'payment',
                ], array_merge($status, [
                    'type' => 'payment',
                    'user_id' => $user->id,
                ]));
            }
        }
    }
}