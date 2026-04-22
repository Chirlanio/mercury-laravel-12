<?php

namespace Database\Factories;

use App\Models\DreManagementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DreManagementLine>
 */
class DreManagementLineFactory extends Factory
{
    protected $model = DreManagementLine::class;

    public function definition(): array
    {
        $sortOrder = fake()->unique()->numberBetween(1000, 9999);

        return [
            'code' => 'L'.str_pad((string) $sortOrder, 4, '0', STR_PAD_LEFT),
            'sort_order' => $sortOrder,
            'is_subtotal' => false,
            'accumulate_until_sort_order' => null,
            'level_1' => '(-) '.fake()->words(3, true),
            'level_2' => null,
            'level_3' => null,
            'level_4' => null,
            'nature' => DreManagementLine::NATURE_EXPENSE,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function subtotal(int $accumulateUntil): static
    {
        return $this->state(fn () => [
            'is_subtotal' => true,
            'accumulate_until_sort_order' => $accumulateUntil,
            'nature' => DreManagementLine::NATURE_SUBTOTAL,
            'level_1' => '(=) '.fake()->words(2, true),
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn () => [
            'nature' => DreManagementLine::NATURE_REVENUE,
            'level_1' => '(+) '.fake()->words(3, true),
        ]);
    }

    public function unclassified(): static
    {
        return $this->state(fn () => [
            'code' => DreManagementLine::UNCLASSIFIED_CODE,
            'sort_order' => 9990,
            'level_1' => '(!) Não classificado',
            'nature' => DreManagementLine::NATURE_EXPENSE,
        ]);
    }
}
