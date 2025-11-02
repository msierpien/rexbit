<?php

namespace Tests\Feature;

use App\Enums\WarehouseDocumentStatus;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDocumentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_document_can_always_be_deleted(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::DRAFT
        ]);

        $this->assertTrue($document->canBeDeleted());
        $this->assertNull($document->getDeletionBlockReason());
    }

    public function test_posted_document_can_be_deleted_if_no_newer_documents_exist(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::POSTED
        ]);

        $this->assertTrue($document->canBeDeleted());
        $this->assertNull($document->getDeletionBlockReason());
    }

    public function test_posted_document_cannot_be_deleted_if_newer_posted_documents_exist(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        // Older document
        $olderDocument = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::POSTED
        ]);

        // Newer document in the same warehouse
        WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::POSTED
        ]);

        $this->assertFalse($olderDocument->fresh()->canBeDeleted());
        $this->assertNotNull($olderDocument->fresh()->getDeletionBlockReason());
    }

    public function test_posted_document_can_be_deleted_if_newer_documents_are_only_drafts(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        // Older posted document
        $olderDocument = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::POSTED
        ]);

        // Newer draft document (should not block deletion)
        WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::DRAFT
        ]);

        $this->assertTrue($olderDocument->fresh()->canBeDeleted());
        $this->assertNull($olderDocument->fresh()->getDeletionBlockReason());
    }

    public function test_draft_documents_can_always_be_edited(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::DRAFT
        ]);

        $this->assertTrue($document->canBeEdited());
    }

    public function test_posted_documents_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
            'status' => WarehouseDocumentStatus::POSTED
        ]);

        $this->assertFalse($document->canBeEdited());
    }
}
