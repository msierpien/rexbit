<?php

namespace Tests\Feature;

use App\Enums\WarehouseDocumentStatus;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_document_can_transition_to_posted(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->draft()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($document->canTransitionTo(WarehouseDocumentStatus::POSTED));
        $this->assertTrue($document->changeStatus(WarehouseDocumentStatus::POSTED, $user));
        $this->assertEquals(WarehouseDocumentStatus::POSTED, $document->fresh()->status);
    }

    public function test_draft_document_can_transition_to_cancelled(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->draft()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($document->canTransitionTo(WarehouseDocumentStatus::CANCELLED));
        $this->assertTrue($document->changeStatus(WarehouseDocumentStatus::CANCELLED, $user));
        $this->assertEquals(WarehouseDocumentStatus::CANCELLED, $document->fresh()->status);
    }

    public function test_posted_document_can_transition_to_cancelled(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->posted()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($document->canTransitionTo(WarehouseDocumentStatus::CANCELLED));
        $this->assertTrue($document->changeStatus(WarehouseDocumentStatus::CANCELLED, $user));
        $this->assertEquals(WarehouseDocumentStatus::CANCELLED, $document->fresh()->status);
    }

    public function test_posted_document_can_transition_to_archived(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->posted()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($document->canTransitionTo(WarehouseDocumentStatus::ARCHIVED));
        $this->assertTrue($document->changeStatus(WarehouseDocumentStatus::ARCHIVED, $user));
        $this->assertEquals(WarehouseDocumentStatus::ARCHIVED, $document->fresh()->status);
    }

    public function test_cancelled_document_can_transition_to_archived(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->cancelled()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($document->canTransitionTo(WarehouseDocumentStatus::ARCHIVED));
        $this->assertTrue($document->changeStatus(WarehouseDocumentStatus::ARCHIVED, $user));
        $this->assertEquals(WarehouseDocumentStatus::ARCHIVED, $document->fresh()->status);
    }

    public function test_archived_document_cannot_transition_to_any_status(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->archived()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertFalse($document->canTransitionTo(WarehouseDocumentStatus::DRAFT));
        $this->assertFalse($document->canTransitionTo(WarehouseDocumentStatus::POSTED));
        $this->assertFalse($document->canTransitionTo(WarehouseDocumentStatus::CANCELLED));
    }

    public function test_invalid_transitions_throw_exception(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->posted()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $document->changeStatus(WarehouseDocumentStatus::DRAFT, $user);
    }

    public function test_status_changes_are_logged_in_metadata(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $document = WarehouseDocument::factory()->draft()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $document->changeStatus(WarehouseDocumentStatus::POSTED, $user);
        
        $document->refresh();
        $this->assertArrayHasKey('status_changes', $document->metadata);
        $this->assertCount(1, $document->metadata['status_changes']);
        
        $change = $document->metadata['status_changes'][0];
        $this->assertEquals('draft', $change['from']);
        $this->assertEquals('posted', $change['to']);
        $this->assertEquals($user->id, $change['changed_by']);
    }

    public function test_only_draft_documents_allow_editing(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $draft = WarehouseDocument::factory()->draft()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);
        
        $posted = WarehouseDocument::factory()->posted()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);
        
        $cancelled = WarehouseDocument::factory()->cancelled()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($draft->canBeEdited());
        $this->assertFalse($posted->canBeEdited());
        $this->assertFalse($cancelled->canBeEdited());
    }

    public function test_deletion_rules_based_on_status(): void
    {
        $user = User::factory()->create();
        $warehouse = WarehouseLocation::factory()->create(['user_id' => $user->id]);
        
        $draft = WarehouseDocument::factory()->draft()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);
        
        $posted = WarehouseDocument::factory()->posted()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);
        
        $cancelled = WarehouseDocument::factory()->cancelled()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);
        
        $archived = WarehouseDocument::factory()->archived()->create([
            'user_id' => $user->id,
            'warehouse_location_id' => $warehouse->id,
        ]);

        $this->assertTrue($draft->canBeDeleted()); // Draft can always be deleted
        $this->assertTrue($posted->canBeDeleted()); // Posted can be deleted if no newer posted docs
        $this->assertTrue($cancelled->canBeDeleted()); // Cancelled can always be deleted  
        $this->assertFalse($archived->canBeDeleted()); // Archived cannot be deleted
    }
}
