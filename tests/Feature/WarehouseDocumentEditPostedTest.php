<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WarehouseDocumentStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentItem;
use App\Models\WarehouseLocation;
use App\Models\WarehouseStockTotal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDocumentEditPostedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private WarehouseDocument $document;
    private Product $product1;
    private Product $product2;
    private WarehouseLocation $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        // Utwórz admina
        $this->admin = User::factory()->create([
            'role' => Role::ADMIN,
        ]);

        // Utwórz zwykłego użytkownika
        $this->user = User::factory()->create([
            'role' => Role::USER,
        ]);

        // Utwórz produkty
        $this->product1 = Product::factory()->create(['user_id' => $this->admin->id]);
        $this->product2 = Product::factory()->create(['user_id' => $this->admin->id]);

        // Utwórz magazyn
        $this->warehouse = WarehouseLocation::factory()->create(['user_id' => $this->admin->id]);

        // Utwórz zatwierdzony dokument PZ
        $this->document = WarehouseDocument::factory()->create([
            'user_id' => $this->admin->id,
            'warehouse_location_id' => $this->warehouse->id,
            'type' => 'PZ',
            'status' => WarehouseDocumentStatus::POSTED,
        ]);

        // Dodaj pozycje
        WarehouseDocumentItem::create([
            'warehouse_document_id' => $this->document->id,
            'product_id' => $this->product1->id,
            'quantity' => 10,
            'unit_price' => 100,
            'vat_rate' => 23,
        ]);

        // Ustaw stan magazynowy (symulacja zatwierdzonego dokumentu)
        WarehouseStockTotal::create([
            'user_id' => $this->admin->id,
            'product_id' => $this->product1->id,
            'warehouse_location_id' => $this->warehouse->id,
            'on_hand' => 10,
        ]);
    }

    public function test_admin_can_edit_posted_document(): void
    {
        $newItems = [
            ['product_id' => $this->product1->id, 'quantity' => 15, 'unit_price' => 100, 'vat_rate' => 23],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => $newItems,
            ]);

        $response->assertRedirect(route('warehouse.documents.show', $this->document));
        $response->assertSessionHas('status');

        // Sprawdź czy pozycje zostały zaktualizowane
        $this->document->refresh();
        $this->assertEquals(1, $this->document->items()->count());
        $this->assertEquals(15, $this->document->items->first()->quantity);

        // Sprawdź czy stan magazynowy został zaktualizowany
        $stock = WarehouseStockTotal::where([
            'user_id' => $this->admin->id,
            'product_id' => $this->product1->id,
            'warehouse_location_id' => $this->warehouse->id,
        ])->first();

        $this->assertEquals(15, $stock->on_hand); // Zmiana z 10 na 15
    }

    public function test_non_admin_cannot_edit_posted_document(): void
    {
        $newItems = [
            ['product_id' => $this->product1->id, 'quantity' => 15, 'unit_price' => 100, 'vat_rate' => 23],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => $newItems,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_admin_can_preview_stock_changes(): void
    {
        $newItems = [
            ['product_id' => $this->product1->id, 'quantity' => 20, 'unit_price' => 100, 'vat_rate' => 23],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('warehouse.documents.preview-edit', $this->document), [
                'items' => $newItems,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $changes = $response->json('changes');
        $this->assertCount(1, $changes);
        $this->assertEquals($this->product1->id, $changes[0]['product_id']);
        $this->assertEquals(10, $changes[0]['old_quantity']);
        $this->assertEquals(20, $changes[0]['new_quantity']);
        $this->assertEquals(10, $changes[0]['net_stock_change']); // +10
        $this->assertEquals(10, $changes[0]['current_stock']);
        $this->assertEquals(20, $changes[0]['new_stock']);
    }

    public function test_edit_posted_document_with_multiple_products(): void
    {
        // Dodaj drugą pozycję do dokumentu
        WarehouseDocumentItem::create([
            'warehouse_document_id' => $this->document->id,
            'product_id' => $this->product2->id,
            'quantity' => 5,
            'unit_price' => 50,
            'vat_rate' => 23,
        ]);

        // Ustaw stan magazynowy dla product2
        WarehouseStockTotal::create([
            'user_id' => $this->admin->id,
            'product_id' => $this->product2->id,
            'warehouse_location_id' => $this->warehouse->id,
            'on_hand' => 5,
        ]);

        $newItems = [
            ['product_id' => $this->product1->id, 'quantity' => 8, 'unit_price' => 100, 'vat_rate' => 23],
            ['product_id' => $this->product2->id, 'quantity' => 12, 'unit_price' => 50, 'vat_rate' => 23],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => $newItems,
            ]);

        $response->assertRedirect(route('warehouse.documents.show', $this->document));

        // Sprawdź pozycje dokumentu
        $this->document->refresh();
        $this->assertEquals(2, $this->document->items()->count());

        // Sprawdź stany magazynowe
        $stock1 = WarehouseStockTotal::where([
            'product_id' => $this->product1->id,
            'warehouse_location_id' => $this->warehouse->id,
        ])->first();
        $this->assertEquals(8, $stock1->on_hand); // 10 -> 8 = -2

        $stock2 = WarehouseStockTotal::where([
            'product_id' => $this->product2->id,
            'warehouse_location_id' => $this->warehouse->id,
        ])->first();
        $this->assertEquals(12, $stock2->on_hand); // 5 -> 12 = +7
    }

    public function test_edit_posted_document_wz_type(): void
    {
        // Utwórz dokument WZ (wydanie)
        $wzDocument = WarehouseDocument::factory()->create([
            'user_id' => $this->admin->id,
            'warehouse_location_id' => $this->warehouse->id,
            'type' => 'WZ',
            'status' => WarehouseDocumentStatus::POSTED,
        ]);

        WarehouseDocumentItem::create([
            'warehouse_document_id' => $wzDocument->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'unit_price' => 100,
            'vat_rate' => 23,
        ]);

        // Ustaw początkowy stan na 20
        $stock = WarehouseStockTotal::where([
            'product_id' => $this->product1->id,
            'warehouse_location_id' => $this->warehouse->id,
        ])->first();
        $stock->on_hand = 20; // 10 (z PZ) + 10 (dodatkowy)
        $stock->save();

        // Edytuj WZ z 5 na 8
        $newItems = [
            ['product_id' => $this->product1->id, 'quantity' => 8, 'unit_price' => 100, 'vat_rate' => 23],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $wzDocument), [
                'items' => $newItems,
            ]);

        $response->assertRedirect();

        // Sprawdź stan magazynowy
        $stock->refresh();
        // 20 (początkowy) + 5 (cofnięcie WZ -5) - 8 (nowe WZ -8) = 20 + 5 - 8 = 17
        $this->assertEquals(17, $stock->on_hand);
    }

    public function test_validation_errors(): void
    {
        // Brak items
        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => [],
            ]);

        $response->assertSessionHasErrors('items');

        // Nieprawidłowe quantity
        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => [
                    ['product_id' => $this->product1->id, 'quantity' => -5, 'unit_price' => 100],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.quantity');

        // Nieistniejący produkt
        $response = $this->actingAs($this->admin)
            ->post(route('warehouse.documents.edit-posted', $this->document), [
                'items' => [
                    ['product_id' => 99999, 'quantity' => 10, 'unit_price' => 100],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.product_id');
    }
}
