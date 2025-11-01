<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\IntegrationImportProfile;
use App\Models\ProductCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationImportProfileFactory extends Factory
{
    protected $model = IntegrationImportProfile::class;

    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'catalog_id' => ProductCatalog::factory(),
            'name' => fake()->words(3, true).' Import Profile',
            'format' => 'csv',
            'source_type' => 'api',
            'source_location' => fake()->url(),
            'delimiter' => ',',
            'has_header' => true,
            'is_active' => true,
            'fetch_mode' => 'manual',
            'fetch_interval_minutes' => null,
            'fetch_daily_at' => null,
            'fetch_cron_expression' => null,
            'next_run_at' => null,
            'last_fetched_at' => null,
            'last_headers' => null,
            'options' => [],
        ];
    }

    public function forProducts(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Products Import',
            'source_type' => 'api',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
