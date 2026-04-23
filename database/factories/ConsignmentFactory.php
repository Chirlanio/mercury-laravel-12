<?php

namespace Database\Factories;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Models\Consignment;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consignment>
 */
class ConsignmentFactory extends Factory
{
    protected $model = Consignment::class;

    public function definition(): array
    {
        $store = Store::query()->first() ?? Store::factory()->create();
        $docClean = fake()->numerify('###########');
        $issuedAt = fake()->dateTimeBetween('-3 days', 'now');
        $issuedDate = $issuedAt instanceof \DateTimeInterface
            ? $issuedAt->format('Y-m-d')
            : (string) $issuedAt;

        return [
            'uuid' => (string) Str::uuid(),
            'type' => ConsignmentType::CLIENTE->value,
            'store_id' => $store->id,
            'employee_id' => null,
            'customer_id' => null,
            'recipient_name' => strtoupper(fake()->name()),
            'recipient_document' => $docClean,
            'recipient_document_clean' => $docClean,
            'recipient_phone' => null,
            'recipient_email' => null,
            'outbound_invoice_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'outbound_invoice_date' => $issuedDate,
            'outbound_store_code' => $store->code,
            'outbound_total_value' => 0,
            'outbound_items_count' => 0,
            'returned_total_value' => 0,
            'returned_items_count' => 0,
            'sold_total_value' => 0,
            'sold_items_count' => 0,
            'lost_total_value' => 0,
            'lost_items_count' => 0,
            'expected_return_date' => (new \DateTime($issuedDate))->modify('+7 days')->format('Y-m-d'),
            'return_period_days' => 7,
            'status' => ConsignmentStatus::PENDING->value,
            'issued_at' => $issuedAt,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ConsignmentStatus::DRAFT->value,
            'issued_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => ConsignmentStatus::PENDING->value,
            'issued_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => ConsignmentStatus::OVERDUE->value,
            'issued_at' => now()->subDays(10),
            'expected_return_date' => now()->subDays(3)->format('Y-m-d'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ConsignmentStatus::COMPLETED->value,
            'issued_at' => now()->subDays(5),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ConsignmentStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancelled_reason' => 'Teste',
        ]);
    }

    public function ofType(ConsignmentType|string $type): static
    {
        $value = $type instanceof ConsignmentType ? $type->value : $type;

        return $this->state(fn () => ['type' => $value]);
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn () => [
            'store_id' => $store->id,
            'outbound_store_code' => $store->code,
        ]);
    }

    public function forRecipientDocument(string $document): static
    {
        $clean = preg_replace('/\D/', '', $document);

        return $this->state(fn () => [
            'recipient_document' => $document,
            'recipient_document_clean' => $clean,
        ]);
    }
}
