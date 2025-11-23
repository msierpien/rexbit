<?php

namespace App\Models;

use App\Enums\WarehouseDocumentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseDocument extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'warehouse_location_id',
        'contractor_id',
        'type',
        'number',
        'issued_at',
        'metadata',
        'status',
        'deleted_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'metadata' => 'array',
        'status' => WarehouseDocumentStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseDocumentItem::class);
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Check if document can be deleted (no newer documents reference it)
     */
    public function canBeDeleted(): bool
    {
        // Use enum method for conditional deletion check
        if (!$this->status->allowsConditionalDeletion()) {
            return false;
        }

        // Draft and cancelled documents can be deleted freely
        if ($this->status->allowsDeletion()) {
            return true;
        }

        // Posted documents can only be deleted if no newer posted documents exist in same warehouse
        if ($this->status === WarehouseDocumentStatus::POSTED) {
            $newerPostedDocuments = static::where('warehouse_location_id', $this->warehouse_location_id)
                ->where('id', '>', $this->id)
                ->where('status', WarehouseDocumentStatus::POSTED)
                ->exists();

            return !$newerPostedDocuments;
        }

        return false;
    }

    /**
     * Get reason why document cannot be deleted
     */
    public function getDeletionBlockReason(): ?string
    {
        if ($this->canBeDeleted()) {
            return null;
        }

        // Check status-based restrictions first
        if (!$this->status->allowsDeletion()) {
            return match ($this->status) {
                WarehouseDocumentStatus::POSTED => "Nie można usunąć zatwierdzonego dokumentu. Najpierw anuluj dokument.",
                WarehouseDocumentStatus::ARCHIVED => "Nie można usunąć zarchiwizowanego dokumentu.",
                default => "Nie można usunąć dokumentu w obecnym statusie.",
            };
        }

        // Check newer documents constraint
        $newerDocumentsCount = static::where('warehouse_location_id', $this->warehouse_location_id)
            ->where('id', '>', $this->id)
            ->where('status', WarehouseDocumentStatus::POSTED)
            ->count();

        if ($newerDocumentsCount > 0) {
            return "Nie można usunąć dokumentu, ponieważ istnieją {$newerDocumentsCount} nowsze zatwierdzone dokumenty w tym magazynie.";
        }

        return "Nie można usunąć tego dokumentu.";
    }

    /**
     * Check if document can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status->allowsEditing();
    }

    /**
     * Check if document can transition to given status
     */
    public function canTransitionTo(WarehouseDocumentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Get available status transitions for this document
     */
    public function getAvailableTransitions(): array
    {
        $allTransitions = WarehouseDocumentStatus::getTransitionRules();
        return $allTransitions[$this->status->value] ?? [];
    }

    /**
     * Change document status with validation
     */
    public function changeStatus(WarehouseDocumentStatus $newStatus, ?User $user = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Nie można zmienić statusu z '{$this->status->label()}' na '{$newStatus->label()}'"
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        
        // Handle stock movements
        if ($oldStatus->affectsStock() !== $newStatus->affectsStock()) {
            $this->handleStockMovementChange($oldStatus, $newStatus);
        }

        $this->save();

        // Log status change if user is provided
        if ($user) {
            $this->logStatusChange($oldStatus, $newStatus, $user);
        }

        return true;
    }

    /**
     * Handle stock movements when status changes
     */
    private function handleStockMovementChange(WarehouseDocumentStatus $oldStatus, WarehouseDocumentStatus $newStatus): void
    {
        if ($oldStatus->affectsStock() && !$newStatus->affectsStock()) {
            // Need to reverse stock movements (cancelling a posted document)
            $this->reverseStockMovements();
        } elseif (!$oldStatus->affectsStock() && $newStatus->affectsStock()) {
            // Need to apply stock movements (posting a draft document)  
            $this->applyStockMovements();
        }
    }

    /**
     * Apply stock movements when document is posted
     */
    private function applyStockMovements(): void
    {
        foreach ($this->items as $item) {
            $stockTotal = WarehouseStockTotal::firstOrCreate([
                'user_id' => $this->user_id,
                'product_id' => $item->product_id,
                'warehouse_location_id' => $this->warehouse_location_id,
            ], [
                'on_hand' => 0,
                'reserved' => 0,
                'incoming' => 0,
            ]);

            if ($this->type === 'RES') {
                $stockTotal->reserved += $item->quantity;
                $stockTotal->save();

                $this->logCustomAction('reservation_created', [
                    'document_type' => $this->type,
                    'document_number' => $this->number,
                    'product_id' => $item->product_id,
                    'reserved_change' => $item->quantity,
                    'new_reserved' => $stockTotal->reserved,
                ]);
            } else {
                $quantityChange = $this->getQuantityChangeForType($item->quantity);

                $stockTotal->on_hand += $quantityChange;
                $stockTotal->save();

                // Log stock movement in audit trail
                $this->logCustomAction('stock_movement_applied', [
                    'document_type' => $this->type,
                    'document_number' => $this->number,
                    'product_id' => $item->product_id,
                    'quantity_change' => $quantityChange,
                    'new_stock_level' => $stockTotal->on_hand,
                ]);
            }

        }
    }

    /**
     * Reverse stock movements when document is cancelled
     */
    private function reverseStockMovements(): void
    {
        foreach ($this->items as $item) {
            $stockTotal = WarehouseStockTotal::where([
                'user_id' => $this->user_id,
                'product_id' => $item->product_id,
                'warehouse_location_id' => $this->warehouse_location_id,
            ])->first();

            if ($stockTotal) {
                if ($this->type === 'RES') {
                    $stockTotal->reserved = max(0, $stockTotal->reserved - $item->quantity);
                    $stockTotal->save();

                    $this->logCustomAction('reservation_released', [
                        'document_type' => $this->type,
                        'document_number' => $this->number,
                        'product_id' => $item->product_id,
                        'reserved_change' => -$item->quantity,
                        'new_reserved' => $stockTotal->reserved,
                    ]);
                } else {
                    $quantityChange = $this->getQuantityChangeForType($item->quantity);
                    
                    // Reverse the movement by subtracting the original change
                    $stockTotal->on_hand -= $quantityChange;
                    
                    // Ensure stock doesn't go negative
                    $stockTotal->on_hand = max(0, $stockTotal->on_hand);
                    $stockTotal->save();

                    // Log stock movement reversal in audit trail
                    $this->logCustomAction('stock_movement_reversed', [
                        'document_type' => $this->type,
                        'document_number' => $this->number,
                        'product_id' => $item->product_id,
                        'quantity_change' => -$quantityChange,
                        'new_stock_level' => $stockTotal->on_hand,
                    ]);
                }
            }
        }
    }

    /**
     * Get quantity change based on document type
     * PZ/IN = positive (receiving goods)
     * WZ/OUT = negative (issuing goods)
     */
    private function getQuantityChangeForType(float $quantity): float
    {
        return in_array($this->type, ['PZ', 'IN']) ? $quantity : -$quantity;
    }

    /**
     * Log status change for audit trail
     */
    private function logStatusChange(WarehouseDocumentStatus $oldStatus, WarehouseDocumentStatus $newStatus, User $user): void
    {
        // Log to audit trail using the Auditable trait
        $this->auditStatusChange($oldStatus->value, $newStatus->value);
        
        // Also log additional context about the status change
        $this->logCustomAction('status_transition', [
            'document_type' => $this->type,
            'document_number' => $this->number,
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'affects_stock' => $newStatus->affectsStock(),
            'can_edit_after' => $newStatus->allowsEditing(),
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);
        
        // Keep metadata for backward compatibility
        $changes = $this->metadata['status_changes'] ?? [];
        $changes[] = [
            'from' => $oldStatus->value,
            'to' => $newStatus->value,
            'changed_by' => $user->id,
            'changed_at' => now()->toISOString(),
        ];
        
        $this->metadata = array_merge($this->metadata ?? [], ['status_changes' => $changes]);
        $this->save(['metadata']);
    }
}
