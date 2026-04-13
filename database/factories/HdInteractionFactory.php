<?php

namespace Database\Factories;

use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdInteraction>
 */
class HdInteractionFactory extends Factory
{
    protected $model = HdInteraction::class;

    public function definition(): array
    {
        return [
            'ticket_id' => HdTicket::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->paragraph(),
            'type' => 'comment',
            'old_value' => null,
            'new_value' => null,
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }

    public function statusChange(string $from, string $to): static
    {
        return $this->state(fn () => [
            'type' => 'status_change',
            'comment' => null,
            'old_value' => $from,
            'new_value' => $to,
        ]);
    }
}
