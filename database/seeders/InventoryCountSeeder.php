<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\Product;
use App\Models\WarehouseStockTotal;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Enums\InventoryCountStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventoryCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find first user
        $user = User::first();
        if (!$user) {
            $this->command->info('No users found. Please create a user first.');
            return;
        }

        // Find or create warehouse
        $warehouse = $user->warehouseLocations()->first();
        if (!$warehouse) {
            $warehouse = $user->warehouseLocations()->create([
                'name' => 'Magazyn główny',
                'code' => 'MAG001',
                'is_default' => true,
                'strict_control' => false,
            ]);
        }

        // Find some products
        $products = $user->products()->whereNotNull('ean')->take(10)->get();
        if ($products->count() < 3) {
            $this->command->info('Not enough products with EAN codes found. Creating some...');
            
            // Create sample products with EAN codes
            $sampleProducts = [
                ['name' => 'Laptop Dell XPS 13', 'sku' => 'DELL-XPS-13', 'ean' => '1234567890123', 'price' => 4999.99],
                ['name' => 'Mysz bezprzewodowa Logitech', 'sku' => 'LOG-MX-Master', 'ean' => '2345678901234', 'price' => 299.99],
                ['name' => 'Klawiatura mechaniczna', 'sku' => 'MECH-KB-87', 'ean' => '3456789012345', 'price' => 599.99],
                ['name' => 'Monitor 27" 4K', 'sku' => 'MON-27-4K', 'ean' => '4567890123456', 'price' => 1999.99],
                ['name' => 'Słuchawki noise-cancelling', 'sku' => 'HP-NC-PRO', 'ean' => '5678901234567', 'price' => 799.99],
            ];

            $products = collect();
            foreach ($sampleProducts as $productData) {
                $product = $user->products()->create([
                    'name' => $productData['name'],
                    'slug' => \Illuminate\Support\Str::slug($productData['name']),
                    'sku' => $productData['sku'],
                    'ean' => $productData['ean'],
                    'purchase_price_net' => $productData['price'],
                    'sale_price_net' => $productData['price'] * 1.3,
                    'purchase_vat_rate' => 23,
                    'sale_vat_rate' => 23,
                    'status' => \App\Enums\ProductStatus::ACTIVE,
                ]);
                $products->push($product);

                // Create stock for this product
                WarehouseStockTotal::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'warehouse_location_id' => $warehouse->id,
                    'on_hand' => rand(5, 50),
                    'reserved' => 0,
                    'incoming' => 0,
                ]);
            }
        }

        // Create sample inventory counts
        $inventoryCounts = [
            [
                'name' => 'Inwentaryzacja Q4 2024',
                'description' => 'Okresowa inwentaryzacja na koniec roku',
                'status' => InventoryCountStatus::DRAFT,
            ],
            [
                'name' => 'Kontrola elektroniki',
                'description' => 'Sprawdzenie stanów sprzętu elektronicznego',
                'status' => InventoryCountStatus::IN_PROGRESS,
            ],
            [
                'name' => 'Inwentaryzacja magazynu głównego',
                'description' => 'Pełna inwentaryzacja wszystkich produktów',
                'status' => InventoryCountStatus::COMPLETED,
            ],
        ];

        foreach ($inventoryCounts as $index => $countData) {
            $inventoryCount = InventoryCount::create([
                'user_id' => $user->id,
                'warehouse_location_id' => $warehouse->id,
                'name' => $countData['name'],
                'description' => $countData['description'],
                'status' => $countData['status'],
                'started_at' => $countData['status'] !== InventoryCountStatus::DRAFT ? now()->subDays($index + 1) : null,
                'completed_at' => $countData['status'] === InventoryCountStatus::COMPLETED ? now()->subHours($index) : null,
                'counted_by' => $countData['status'] !== InventoryCountStatus::DRAFT ? $user->id : null,
            ]);

            // Add items for non-draft inventories
            if ($countData['status'] !== InventoryCountStatus::DRAFT) {
                $productsToCount = $products->take(rand(3, 5));
                
                foreach ($productsToCount as $product) {
                    $stock = WarehouseStockTotal::where('product_id', $product->id)
                        ->where('warehouse_location_id', $warehouse->id)
                        ->first();
                    
                    $systemQuantity = $stock ? $stock->on_hand : 0;
                    
                    // Simulate some discrepancies
                    $discrepancyChance = rand(1, 100);
                    if ($discrepancyChance <= 30) { // 30% chance of discrepancy
                        $countedQuantity = $systemQuantity + rand(-5, 5);
                    } else {
                        $countedQuantity = $systemQuantity;
                    }
                    
                    $countedQuantity = max(0, $countedQuantity);

                    InventoryCountItem::create([
                        'inventory_count_id' => $inventoryCount->id,
                        'product_id' => $product->id,
                        'system_quantity' => $systemQuantity,
                        'counted_quantity' => $countedQuantity,
                        'unit_cost' => $product->purchase_price_net ?? 0,
                        'counted_at' => now()->subHours(rand(1, 24)),
                        'scanned_ean' => rand(1, 100) <= 70 ? $product->ean : null, // 70% scanned, 30% manual
                    ]);
                }
            }
        }

        $this->command->info('Sample inventory counts created successfully!');
        $this->command->info("Created {$products->count()} products with EAN codes");
        $this->command->info('Created 3 inventory counts in different statuses');
    }
}
