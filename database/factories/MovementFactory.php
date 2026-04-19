<?php

namespace Database\Factories;

use App\Models\Movement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    protected $model = Movement::class;

    public function definition(): array
    {
        $realized = $this->faker->randomFloat(2, 10, 5000);
        $quantity = $this->faker->randomFloat(3, 1, 10);
        $movementCode = 2;
        $entryExit = 'S';

        [$netValue, $netQuantity] = Movement::calculateNetValues($realized, $quantity, $movementCode, $entryExit);

        return [
            'movement_date' => $this->faker->date(),
            'movement_time' => $this->faker->time(),
            'store_code' => 'Z'.$this->faker->numerify('###'),
            'cpf_customer' => $this->faker->numerify('###########'),
            'invoice_number' => (string) $this->faker->numberBetween(1000, 99999),
            'movement_code' => $movementCode,
            'cpf_consultant' => $this->faker->numerify('###########'),
            'ref_size' => strtoupper($this->faker->bothify('??###-##')),
            'barcode' => $this->faker->ean13(),
            'sale_price' => $realized,
            'cost_price' => $realized * 0.6,
            'realized_value' => $realized,
            'discount_value' => 0,
            'quantity' => $quantity,
            'entry_exit' => $entryExit,
            'net_value' => $netValue,
            'net_quantity' => $netQuantity,
            'sync_batch_id' => $this->faker->uuid(),
            'synced_at' => now(),
        ];
    }

    public function sale(): static
    {
        return $this->state(function () {
            return ['movement_code' => 2, 'entry_exit' => 'S'];
        })->afterMaking(fn ($m) => $this->recalcNet($m))
          ->afterCreating(fn ($m) => $this->recalcNet($m, persist: true));
    }

    public function returnEntry(): static
    {
        return $this->state(function () {
            return ['movement_code' => 6, 'entry_exit' => 'E'];
        })->afterMaking(fn ($m) => $this->recalcNet($m))
          ->afterCreating(fn ($m) => $this->recalcNet($m, persist: true));
    }

    public function forStore(string $storeCode): static
    {
        return $this->state(fn () => ['store_code' => $storeCode]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn () => ['movement_date' => $date]);
    }

    public function forInvoice(string $invoiceNumber, ?string $storeCode = null): static
    {
        return $this->state(function () use ($invoiceNumber, $storeCode) {
            $attrs = ['invoice_number' => $invoiceNumber];
            if ($storeCode) {
                $attrs['store_code'] = $storeCode;
            }

            return $attrs;
        });
    }

    public function unsynced(): static
    {
        return $this->state(fn () => ['synced_at' => null]);
    }

    protected function recalcNet(Movement $movement, bool $persist = false): void
    {
        [$netValue, $netQuantity] = Movement::calculateNetValues(
            (float) $movement->realized_value,
            (float) $movement->quantity,
            (int) $movement->movement_code,
            (string) $movement->entry_exit,
        );

        $movement->net_value = $netValue;
        $movement->net_quantity = $netQuantity;

        if ($persist) {
            $movement->saveQuietly();
        }
    }
}
