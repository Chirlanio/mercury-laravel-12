<?php

namespace Database\Factories;

use App\Enums\ConsignmentItemStatus;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConsignmentItem>
 */
class ConsignmentItemFactory extends Factory
{
    protected $model = ConsignmentItem::class;

    public function definition(): array
    {
        // Product precisa existir — FK NOT NULL (regra M8). Criamos um
        // product de teste se nenhum foi informado via state.
        $product = Product::query()->inRandomOrder()->first();
        if (! $product) {
            $product = Product::create([
                'reference' => 'REF-'.fake()->unique()->numerify('#####'),
                'description' => fake()->words(3, true),
                'sale_price' => fake()->randomFloat(2, 50, 500),
                'is_active' => true,
            ]);
        }

        $variant = ProductVariant::query()->where('product_id', $product->id)->first();
        if (! $variant) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'barcode' => fake()->numerify('#############'),
                'size_cigam_code' => 'U'.fake()->numberBetween(34, 42),
                'is_active' => true,
            ]);
        }

        $quantity = fake()->numberBetween(1, 5);
        $unitValue = fake()->randomFloat(2, 50, 500);

        return [
            'consignment_id' => Consignment::factory(),
            'movement_id' => null,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'reference' => $product->reference,
            'barcode' => $variant->barcode,
            'size_label' => str_replace('U', '', $variant->size_cigam_code ?? ''),
            'size_cigam_code' => $variant->size_cigam_code,
            'description' => $product->description,
            'quantity' => $quantity,
            'unit_value' => $unitValue,
            'total_value' => round($quantity * $unitValue, 2),
            'returned_quantity' => 0,
            'sold_quantity' => 0,
            'lost_quantity' => 0,
            'status' => ConsignmentItemStatus::PENDING->value,
        ];
    }

    public function forConsignment(Consignment $consignment): static
    {
        return $this->state(fn () => ['consignment_id' => $consignment->id]);
    }

    public function forProduct(Product $product, ?ProductVariant $variant = null): static
    {
        $variant = $variant ?? ProductVariant::query()->where('product_id', $product->id)->first();

        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'reference' => $product->reference,
            'barcode' => $variant?->barcode,
            'size_cigam_code' => $variant?->size_cigam_code,
            'description' => $product->description,
        ]);
    }

    public function quantity(int $qty, float $unitValue = 100.00): static
    {
        return $this->state(fn () => [
            'quantity' => $qty,
            'unit_value' => $unitValue,
            'total_value' => round($qty * $unitValue, 2),
        ]);
    }
}
