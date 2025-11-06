<?php

namespace Tests\Feature;

use App\Enums\InventoryCountStatus;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCountScannerTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $user, string $ean, string $suffix): Product
    {
        return Product::create([
            'user_id' => $user->id,
            'slug' => 'product-' . $suffix,
            'sku' => 'SKU-' . $suffix,
            'name' => 'Test product ' . $suffix,
            'ean' => $ean,
            'status' => ProductStatus::ACTIVE,
            'purchase_price_net' => 10,
            'purchase_vat_rate' => 23,
            'sale_price_net' => 20,
            'sale_vat_rate' => 23,
        ]);
    }

    public function test_scanner_endpoint_limits_products_to_authenticated_user(): void
    {
        $ean = '5902230771994';

        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);

        $ownProduct = $this->createProduct($user, $ean, 'own');
        $this->createProduct($otherUser, $ean, 'foreign');

        $response = $this->actingAs($user)
            ->postJson(route('inventory-counts.find-product-by-ean'), ['ean' => $ean]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'product' => ['id' => $ownProduct->id],
            ]);
    }

    public function test_update_quantity_matches_product_by_ean_for_inventory_owner(): void
    {
        $ean = '5902230771994';

        $user = User::factory()->create(['role' => Role::USER]);
        $otherUser = User::factory()->create(['role' => Role::USER]);
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);

        $ownProduct = $this->createProduct($user, $ean, 'own');
        $this->createProduct($otherUser, $ean, 'foreign');

        $inventoryCount = InventoryCount::create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'name' => 'Test inventory',
            'status' => InventoryCountStatus::IN_PROGRESS,
        ]);

        $response = $this->actingAs($user)->postJson(
            route('inventory-counts.update-quantity', $inventoryCount),
            [
                'ean' => $ean,
                'counted_quantity' => 5,
            ]
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'item' => [
                    'product_id' => $ownProduct->id,
                    'counted_quantity' => 5.0,
                ],
            ]);
    }
}
