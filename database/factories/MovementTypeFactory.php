<?php

namespace Database\Factories;

use App\Models\MovementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementType>
 */
class MovementTypeFactory extends Factory
{
    protected $model = MovementType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numberBetween(1, 999),
            'description' => $this->faker->words(2, true),
            'synced_at' => now(),
        ];
    }

    public function sales(): static
    {
        return $this->state(fn () => [
            'code' => 2,
            'description' => 'Vendas',
        ]);
    }

    public function returns(): static
    {
        return $this->state(fn () => [
            'code' => 6,
            'description' => 'Devoluções',
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => [
            'code' => 1,
            'description' => 'Compra',
        ]);
    }
}
