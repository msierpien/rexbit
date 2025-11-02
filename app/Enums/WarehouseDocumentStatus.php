<?php

namespace App\Enums;

enum WarehouseDocumentStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Roboczy',
            self::POSTED => 'Zatwierdzony',
            self::CANCELLED => 'Anulowany',
            self::ARCHIVED => 'Zarchiwizowany',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::DRAFT => in_array($status, [self::POSTED, self::CANCELLED]),
            self::POSTED => in_array($status, [self::CANCELLED, self::ARCHIVED]),
            self::CANCELLED => $status === self::ARCHIVED,
            self::ARCHIVED => false, // Archived documents cannot change status
        };
    }

    public function allowsEditing(): bool
    {
        return $this === self::DRAFT;
    }

    public function allowsDeletion(): bool
    {
        return in_array($this, [self::DRAFT, self::CANCELLED]);
    }

    public function allowsConditionalDeletion(): bool
    {
        // These statuses can be deleted under certain conditions (like no newer documents)
        return in_array($this, [self::DRAFT, self::POSTED, self::CANCELLED]);
    }

    public function affectsStock(): bool
    {
        return $this === self::POSTED;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-600',
            self::POSTED => 'bg-green-100 text-green-700',
            self::CANCELLED => 'bg-red-100 text-red-700',
            self::ARCHIVED => 'bg-blue-100 text-blue-700',
        };
    }

    public static function getTransitionRules(): array
    {
        return [
            self::DRAFT->value => [
                'post' => 'Zatwierdź dokument',
                'cancel' => 'Anuluj dokument',
            ],
            self::POSTED->value => [
                'cancel' => 'Anuluj zatwierdzony dokument',
                'archive' => 'Zarchiwizuj dokument',
            ],
            self::CANCELLED->value => [
                'archive' => 'Zarchiwizuj anulowany dokument',
            ],
            self::ARCHIVED->value => [],
        ];
    }
}