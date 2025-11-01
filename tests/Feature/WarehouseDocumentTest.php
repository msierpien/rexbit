<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseLocation;
use App\Models\Contractor;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected WarehouseLocation $warehouse;
    protected Contractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => Role::ADMIN]);
        $this->warehouse = WarehouseLocation::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->contractor = Contractor::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_view_warehouse_documents_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('warehouse.documents.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Warehouse/Documents/Index'));
    }

    public function test_user_can_view_create_warehouse_document_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('warehouse.documents.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Warehouse/Documents/Create'));
    }

    public function test_user_can_create_warehouse_document(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('warehouse.documents.store'), [
                'number' => 'WD-001',
                'type' => 'PZ',
                'warehouse_location_id' => $this->warehouse->id,
                'contractor_id' => $this->contractor->id,
                'issued_at' => now()->format('Y-m-d'),
                'status' => 'draft',
                'items' => [],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('warehouse_documents', [
            'number' => 'WD-001',
            'type' => 'PZ',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_update_warehouse_document(): void
    {
        $document = WarehouseDocument::factory()->create([
            'user_id' => $this->user->id,
            'number' => 'WD-001',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('warehouse.documents.update', $document), [
                'number' => 'WD-002',
                'type' => $document->type,
                'warehouse_location_id' => $document->warehouse_location_id,
                'contractor_id' => $document->contractor_id,
                'issued_at' => $document->issued_at->format('Y-m-d'),
                'status' => $document->status,
                'items' => [],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('warehouse_documents', [
            'id' => $document->id,
            'number' => 'WD-002',
        ]);
    }

    public function test_user_can_delete_warehouse_document(): void
    {
        $document = WarehouseDocument::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('warehouse.documents.destroy', $document));

        $response->assertRedirect();
        $this->assertDatabaseMissing('warehouse_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_user_cannot_access_other_users_warehouse_document(): void
    {
        $otherUser = User::factory()->create(['role' => Role::USER]);
        $document = WarehouseDocument::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('warehouse.documents.edit', $document));

        $response->assertStatus(403);
    }
}
