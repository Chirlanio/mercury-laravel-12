<?php

namespace Database\Factories;

use App\Models\HdDepartment;
use App\Models\HdPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdPermission>
 */
class HdPermissionFactory extends Factory
{
    protected $model = HdPermission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => HdDepartment::factory(),
            'level' => 'technician',
        ];
    }

    public function technician(): static
    {
        return $this->state(fn () => ['level' => 'technician']);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['level' => 'manager']);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forDepartment(HdDepartment $department): static
    {
        return $this->state(fn () => ['department_id' => $department->id]);
    }
}
