<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentAttachment;
use App\Models\User;
use App\Notifications\StockAdjustment\StockAdjustmentCreatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class StockAdjustmentService
{
    public const EDITABLE_STATUSES = ['pending', 'awaiting_response'];

    /**
     * Cria um ajuste com seus itens.
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data, User $user): StockAdjustment
    {
        $adjustment = DB::transaction(function () use ($data, $user) {
            $created = StockAdjustment::create([
                'store_id' => $data['store_id'],
                'employee_id' => $data['employee_id'] ?? null,
                'status' => 'pending',
                'observation' => $data['observation'] ?? null,
                'client_name' => $data['client_name'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            $this->syncItems($created, $data['items'] ?? []);

            return $created->fresh(['items.reason', 'store', 'employee', 'createdBy']);
        });

        // Notifica aprovadores (admins) — best-effort, não quebra o fluxo
        try {
            $recipients = $this->approverRecipients();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new StockAdjustmentCreatedNotification($adjustment));
            }
        } catch (\Throwable $e) {
            // Ignora falhas de notificação
        }

        return $adjustment;
    }

    /**
     * Retorna usuários que devem ser notificados de um novo ajuste:
     * usuários com permissão EDIT_ADJUSTMENTS (aprovadores).
     */
    public function approverRecipients()
    {
        return User::query()
            ->whereIn('role', ['super_admin', 'admin'])
            ->get();
    }

    /**
     * Atualiza um ajuste. Só ajustes em estado editável.
     */
    public function update(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        if (! in_array($adjustment->status, self::EDITABLE_STATUSES, true)) {
            throw new InvalidArgumentException('Apenas ajustes em estados editáveis podem ser alterados.');
        }

        return DB::transaction(function () use ($adjustment, $data) {
            $adjustment->update([
                'employee_id' => $data['employee_id'] ?? null,
                'observation' => $data['observation'] ?? null,
                'client_name' => $data['client_name'] ?? null,
            ]);

            $adjustment->items()->delete();
            $this->syncItems($adjustment, $data['items'] ?? []);

            return $adjustment->fresh(['items.reason', 'store', 'employee']);
        });
    }

    /**
     * Soft-delete do ajuste.
     */
    public function delete(StockAdjustment $adjustment, User $user, ?string $reason = null): void
    {
        if (! in_array($adjustment->status, ['pending', 'cancelled'], true)) {
            throw new InvalidArgumentException('Apenas ajustes pendentes ou cancelados podem ser excluídos.');
        }

        $adjustment->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $user->id,
            'delete_reason' => $reason ?: 'Excluído pelo usuário',
        ]);
    }

    /**
     * Anexa um arquivo ao ajuste.
     */
    public function addAttachment(StockAdjustment $adjustment, UploadedFile $file, User $user): StockAdjustmentAttachment
    {
        $stored = $file->store("stock-adjustments/{$adjustment->id}", 'local');

        return StockAdjustmentAttachment::create([
            'stock_adjustment_id' => $adjustment->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'file_path' => $stored,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by_user_id' => $user->id,
        ]);
    }

    public function removeAttachment(StockAdjustmentAttachment $attachment): void
    {
        if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }
        $attachment->delete();
    }

    /**
     * Sincroniza (recria) os itens do ajuste.
     *
     * @param  array<int, array<string,mixed>>  $items
     */
    private function syncItems(StockAdjustment $adjustment, array $items): void
    {
        foreach ($items as $index => $item) {
            $adjustment->items()->create([
                'reference' => $item['reference'],
                'size' => $item['size'] ?? null,
                'direction' => $item['direction'] ?? 'increase',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'current_stock' => isset($item['current_stock']) ? (int) $item['current_stock'] : null,
                'reason_id' => $item['reason_id'] ?? null,
                'notes' => $item['notes'] ?? null,
                'is_adjustment' => $item['is_adjustment'] ?? true,
                'sort_order' => $index,
            ]);
        }
    }
}
