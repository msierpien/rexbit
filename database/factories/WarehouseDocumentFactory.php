<?php

namespace Database\Factories;

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
            'status' => fake()->randomElement(['draft', 'confirmed', 'completed']),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
