<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'name' => strtoupper($firstName . ' ' . $lastName),
            'short_name' => strtoupper($firstName),
            'cpf' => fake()->unique()->numerify('###########'),
            'admission_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-18 years'),
            'position_id' => 1,
            'store_id' => 'Z999',
            'status_id' => 2, // Active
            'level' => fake()->randomElement(['Junior', 'Pleno', 'Senior']),
            'education_level_id' => fake()->numberBetween(1, 5),
            'gender_id' => fake()->numberBetween(1, 2),
            'is_pcd' => false,
            'is_apprentice' => false,
        ];
    }

    /**
     * Indicate that the employee is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => 3,
            'dismissal_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the employee is a PCD.
     */
    public function pcd(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pcd' => true,
        ]);
    }

    /**
     * Indicate that the employee is an apprentice.
     */
    public function apprentice(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_apprentice' => true,
        ]);
    }
}
