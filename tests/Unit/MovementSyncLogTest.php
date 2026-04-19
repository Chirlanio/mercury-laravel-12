<?php

namespace Tests\Unit;

use App\Models\MovementSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementSyncLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_running_log(): void
    {
        $user = User::factory()->create();
        $log = MovementSyncLog::start('auto', $user->id);

        $this->assertSame('running', $log->status);
        $this->assertSame('auto', $log->sync_type);
        $this->assertSame($user->id, $log->started_by_user_id);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->completed_at);
    }

    public function test_mark_completed_sets_status_and_completed_at(): void
    {
        $log = MovementSyncLog::start('today');
        $log->markCompleted();

        $log->refresh();

        $this->assertSame('completed', $log->status);
        $this->assertNotNull($log->completed_at);
    }

    public function test_mark_failed_stores_message_in_error_details(): void
    {
        $log = MovementSyncLog::start('range');
        $log->markFailed('Timeout na conexão CIGAM');

        $log->refresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame('Timeout na conexão CIGAM', $log->error_details['message']);
        $this->assertSame(1, $log->error_count);
    }

    public function test_mark_failed_preserves_existing_record_errors(): void
    {
        $log = MovementSyncLog::start('range');
        $log->pushError(['message' => 'erro 1']);
        $log->pushError(['message' => 'erro 2']);

        $log->markFailed('falha geral');
        $log->refresh();

        $this->assertSame('falha geral', $log->error_details['message']);
        $this->assertCount(2, $log->error_details['records']);
    }

    public function test_push_error_increments_error_count_and_skipped(): void
    {
        $log = MovementSyncLog::start('range');

        $log->pushError([
            'phase' => 'map',
            'record_date' => '2026-04-01',
            'store' => 'Z001',
            'message' => 'CPF inválido',
        ]);

        $log->refresh();

        $this->assertSame(1, $log->error_count);
        $this->assertSame(1, $log->skipped_records);
        $this->assertCount(1, $log->error_details['records']);
        $this->assertSame('Z001', $log->error_details['records'][0]['store']);
        $this->assertArrayHasKey('timestamp', $log->error_details['records'][0]);
    }

    public function test_push_error_caps_at_max_records_and_counts_truncated(): void
    {
        $log = MovementSyncLog::start('range');

        for ($i = 0; $i < MovementSyncLog::MAX_ERROR_RECORDS + 5; $i++) {
            $log->pushError(['message' => "erro {$i}"]);
        }

        $log->refresh();

        $this->assertCount(MovementSyncLog::MAX_ERROR_RECORDS, $log->error_details['records']);
        $this->assertSame(5, $log->error_details['truncated']);
        $this->assertSame(MovementSyncLog::MAX_ERROR_RECORDS + 5, $log->error_count);
    }

    public function test_merge_deletion_summary_accumulates_across_chunks(): void
    {
        $log = MovementSyncLog::start('range');

        $log->mergeDeletionSummary([
            'by_store' => ['Z001' => 10, 'Z002' => 5],
            'by_date' => ['2026-04-01' => 15],
            'by_movement_code' => [2 => 12, 6 => 3],
            'total' => 15,
        ]);

        $log->mergeDeletionSummary([
            'by_store' => ['Z001' => 3, 'Z003' => 7],
            'by_date' => ['2026-04-02' => 10],
            'by_movement_code' => [2 => 10],
            'total' => 10,
        ]);

        $log->refresh();
        $summary = $log->deletion_summary;

        $this->assertSame(25, $summary['total']);
        $this->assertSame(13, $summary['by_store']['Z001']);
        $this->assertSame(5, $summary['by_store']['Z002']);
        $this->assertSame(7, $summary['by_store']['Z003']);
        $this->assertSame(15, $summary['by_date']['2026-04-01']);
        $this->assertSame(10, $summary['by_date']['2026-04-02']);
        $this->assertSame(22, $summary['by_movement_code'][2]);
    }
}
