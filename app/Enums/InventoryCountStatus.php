<?php

namespace App\Enums;

enum InventoryCountStatus: string
{
    case DRAFT = 'draft';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case APPROVED = 'approved';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Projekt',
            self::IN_PROGRESS => 'W trakcie',
            self::COMPLETED => 'Zakończona',
            self::APPROVED => 'Zatwierdzona',
            self::CANCELLED => 'Anulowana',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'slate',
            self::IN_PROGRESS => 'blue',
            self::COMPLETED => 'amber',
            self::APPROVED => 'emerald',
            self::CANCELLED => 'red',
        };
    }

    /**
     * Check if the status allows editing
     */
    public function allowsEditing(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::IN_PROGRESS,
        ]);
    }

    /**
     * Check if the status allows deletion
     */
    public function allowsDeletion(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::CANCELLED,
        ]);
    }

    /**
     * Check if inventory can be started
     */
    public function canBeStarted(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if inventory can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if inventory can be approved
     */
    public function canBeApproved(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if inventory can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::IN_PROGRESS,
        ]);
    }

    /**
     * Get possible next statuses
     */
    public function possibleNextStatuses(): array
    {
        return match ($this) {
            self::DRAFT => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [self::APPROVED, self::IN_PROGRESS], // można wrócić do poprawek
            self::APPROVED => [], // status końcowy
            self::CANCELLED => [], // status końcowy
        };
    }
}