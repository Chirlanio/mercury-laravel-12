<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'Z' . fake()->unique()->numerify('###'),
            'name' => fake()->company(),
            'cnpj' => fake()->numerify('##############'),
            'company_name' => strtoupper(fake()->company() . ' LTDA'),
            'state_registration' => fake()->numerify('#########'),
            'address' => strtoupper(fake()->address()),
            'network_id' => fake()->numberBetween(1, 8),
            'manager_id' => 1,
            'supervisor_id' => 1,
            'store_order' => fake()->numberBetween(1, 10),
            'network_order' => fake()->numberBetween(1, 8),
            'status_id' => 1,
        ];
    }

    /**
     * Indicate that the store is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => 2,
        ]);
    }

    /**
     * Indicate that the store belongs to a specific network.
     */
    public function network(int $networkId): static
    {
        return $this->state(fn (array $attributes) => [
            'network_id' => $networkId,
        ]);
    }
}
