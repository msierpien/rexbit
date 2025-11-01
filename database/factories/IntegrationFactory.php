<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\User;
use App\Enums\IntegrationType;
use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' Integration',
            'type' => IntegrationType::PRESTASHOP,
            'status' => IntegrationStatus::ACTIVE,
            'config' => [
                'api_url' => fake()->url(),
                'api_key' => fake()->uuid(),
            ],
            'meta' => [],
            'last_synced_at' => null,
        ];
    }

    public function prestashop(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IntegrationType::PRESTASHOP,
            'config' => [
                'api_url' => fake()->url(),
                'api_key' => fake()->uuid(),
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IntegrationStatus::INACTIVE,
        ]);
    }
}
