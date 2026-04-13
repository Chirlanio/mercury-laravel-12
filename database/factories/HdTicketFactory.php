<?php

namespace Database\Factories;

use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdTicket>
 */
class HdTicketFactory extends Factory
{
    protected $model = HdTicket::class;

    public function definition(): array
    {
        $priority = HdTicket::PRIORITY_MEDIUM;

        return [
            'requester_id' => User::factory(),
            'assigned_technician_id' => null,
            'department_id' => HdDepartment::factory(),
            'category_id' => null,
            'store_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status' => HdTicket::STATUS_OPEN,
            'priority' => $priority,
            'sla_due_at' => now()->addHours(HdTicket::SLA_HOURS[$priority]),
            'resolved_at' => null,
            'closed_at' => null,
            'created_by_user_id' => fn (array $attr) => $attr['requester_id'],
            'updated_by_user_id' => null,
        ];
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn () => [
            'priority' => $priority,
            'sla_due_at' => now()->addHours(HdTicket::SLA_HOURS[$priority] ?? 48),
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(function () use ($status) {
            $state = ['status' => $status];
            if ($status === HdTicket::STATUS_RESOLVED) {
                $state['resolved_at'] = now();
            }
            if ($status === HdTicket::STATUS_CLOSED) {
                $state['resolved_at'] = now()->subMinutes(10);
                $state['closed_at'] = now();
            }

            return $state;
        });
    }

    public function assignedTo(User $technician): static
    {
        return $this->state(fn () => ['assigned_technician_id' => $technician->id]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['sla_due_at' => now()->subHour()]);
    }
}
