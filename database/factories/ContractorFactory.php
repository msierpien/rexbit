<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractorFactory extends Factory
{
    protected $model = Contractor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'tax_id' => fake()->numerify('##########'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'is_supplier' => true,
            'is_customer' => false,
            'meta' => [],
        ];
    }

    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_supplier' => true,
            'is_customer' => false,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_supplier' => false,
            'is_customer' => true,
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_supplier' => true,
            'is_customer' => true,
        ]);
    }
}
