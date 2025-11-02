<?php

namespace Database\Factories;

use App\Enums\WarehouseDocumentStatus;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseLocation;
use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseDocumentFactory extends Factory
{
    protected $model = WarehouseDocument::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'warehouse_location_id' => WarehouseLocation::factory(),
            'contractor_id' => Contractor::factory(),
            'type' => fake()->randomElement(['PZ', 'WZ', 'MM', 'RW']),
            'number' => 'WD-'.fake()->unique()->numerify('####'),
            'issued_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'metadata' => [],
            'status' => fake()->randomElement([WarehouseDocumentStatus::DRAFT, WarehouseDocumentStatus::POSTED]),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WarehouseDocumentStatus::DRAFT,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WarehouseDocumentStatus::POSTED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WarehouseDocumentStatus::CANCELLED,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WarehouseDocumentStatus::ARCHIVED,
        ]);
    }
}
