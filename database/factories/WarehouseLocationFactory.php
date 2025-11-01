<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' Warehouse',
            'code' => strtoupper(fake()->lexify('WH-???')),
            'is_default' => false,
            'strict_control' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function strictControl(): static
    {
        return $this->state(fn (array $attributes) => [
            'strict_control' => true,
        ]);
    }
}
