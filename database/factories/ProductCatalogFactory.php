<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ProductCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCatalogFactory extends Factory
{
    protected $model = ProductCatalog::class;

    public function definition(): array
    {
        $name = fake()->words(2, true).' Catalog';
        
        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
