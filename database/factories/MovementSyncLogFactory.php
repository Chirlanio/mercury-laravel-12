<?php

namespace Database\Factories;

use App\Models\MovementSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementSyncLog>
 */
class MovementSyncLogFactory extends Factory
{
    protected $model = MovementSyncLog::class;

    public function definition(): array
    {
        return [
            'sync_type' => 'auto',
            'status' => 'running',
            'total_records' => 0,
            'processed_records' => 0,
            'inserted_records' => 0,
            'deleted_records' => 0,
            'skipped_records' => 0,
            'error_count' => 0,
            'started_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function failed(string $message = 'error'): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => ['message' => $message],
            'error_count' => 1,
        ]);
    }
}
