<?php

namespace App\Services;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\TravelExpenseStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Gestão de itens de prestação de contas. Adiciona, edita e remove itens
 * (e seus comprovantes), e dispara transição automática
 * pending → in_progress no primeiro item.
 *
 * Submissão da prestação completa (in_progress → submitted) e aprovação
 * (submitted → approved/rejected) são feitas via TransitionService —
 * este aqui só lida com os itens e o switch automático pending↔in_progress.
 *
 * Uploads:
 *  - disk 'public', diretório travel-expenses/{ulid}/
 *  - arquivo nomeado com timestamp + slug do original
 *  - validação de mime/tamanho fica no FormRequest do controller
 */
class TravelExpenseAccountabilityService
{
    /**
     * Tipos MIME aceitos para comprovantes.
     */
    public const ACCEPTED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Tamanho máximo de upload (5MB).
     */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    public function __construct(
        protected TravelExpenseTransitionService $transition,
    ) {}

    /**
     * Adiciona um item à prestação. Bloqueado se a verba não estiver
     * APROVADA, ou se a prestação já estiver submetida/aprovada.
     *
     * Campos esperados em $data:
     *  - type_expense_id (int, required)
     *  - expense_date (Y-m-d, required)
     *  - value (decimal, required)
     *  - description (string, required)
     *  - invoice_number (string, optional)
     *  - attachment (UploadedFile, optional)
     *
     * @throws ValidationException
     */
    public function addItem(TravelExpense $te, array $data, User $actor): TravelExpenseItem
    {
        $this->ensureCanModifyAccountability($te, $actor);

        if (! $this->canAddOrEditItems($te)) {
            throw ValidationException::withMessages([
                'accountability' => 'Itens só podem ser adicionados quando a verba está aprovada e a prestação está em rascunho/em andamento.',
            ]);
        }

        $attachment = $data['attachment'] ?? null;
        if ($attachment instanceof UploadedFile) {
            $this->validateUpload($attachment);
        }

        return DB::transaction(function () use ($te, $data, $actor, $attachment) {
            $item = new TravelExpenseItem([
                'travel_expense_id' => $te->id,
                'type_expense_id' => $data['type_expense_id'],
                'expense_date' => $data['expense_date'],
                'value' => $data['value'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'],
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            if ($attachment instanceof UploadedFile) {
                $this->fillAttachmentFields($item, $attachment, $te);
            }

            $item->save();

            // Auto-transição pending → in_progress no primeiro item
            if ($te->accountability_status === AccountabilityStatus::PENDING) {
                $this->autoTransitionAccountability(
                    $te,
                    AccountabilityStatus::IN_PROGRESS,
                    $actor,
                    'Primeiro item de prestação adicionado'
                );
            }

            return $item->fresh(['typeExpense', 'createdBy']);
        });
    }

    /**
     * @throws ValidationException
     */
    public function updateItem(
        TravelExpenseItem $item,
        array $data,
        User $actor
    ): TravelExpenseItem {
        $te = $item->travelExpense;
        $this->ensureCanModifyAccountability($te, $actor);

        if (! $this->canAddOrEditItems($te)) {
            throw ValidationException::withMessages([
                'accountability' => 'Itens só podem ser editados enquanto a prestação estiver em rascunho/em andamento.',
            ]);
        }

        $attachment = $data['attachment'] ?? null;
        if ($attachment instanceof UploadedFile) {
            $this->validateUpload($attachment);
        }

        return DB::transaction(function () use ($item, $data, $actor, $attachment, $te) {
            $allowedFields = [
                'type_expense_id', 'expense_date', 'value',
                'invoice_number', 'description',
            ];
            $update = array_intersect_key($data, array_flip($allowedFields));
            $update['updated_by_user_id'] = $actor->id;

            $item->fill($update);

            if ($attachment instanceof UploadedFile) {
                // Apaga arquivo antigo, se houver
                if ($item->attachment_path) {
                    Storage::disk('public')->delete($item->attachment_path);
                }

                $this->fillAttachmentFields($item, $attachment, $te);
            }

            $item->save();

            return $item->fresh(['typeExpense']);
        });
    }

    /**
     * Soft-delete de item. Auto-transição in_progress → pending se foi
     * o último item ativo.
     *
     * @throws ValidationException
     */
    public function deleteItem(TravelExpenseItem $item, User $actor): void
    {
        $te = $item->travelExpense;
        $this->ensureCanModifyAccountability($te, $actor);

        if (! $this->canAddOrEditItems($te)) {
            throw ValidationException::withMessages([
                'accountability' => 'Itens só podem ser removidos enquanto a prestação estiver em rascunho/em andamento.',
            ]);
        }

        DB::transaction(function () use ($item, $actor, $te) {
            $item->update([
                'deleted_at' => now(),
                'deleted_by_user_id' => $actor->id,
            ]);

            // Se foi o último item, volta a prestação pra pending
            $remaining = $te->items()->count();
            if ($remaining === 0
                && $te->accountability_status === AccountabilityStatus::IN_PROGRESS) {
                $this->autoTransitionAccountability(
                    $te,
                    AccountabilityStatus::PENDING,
                    $actor,
                    'Último item removido — prestação voltou para Aguardando'
                );
            }
        });
    }

    /**
     * Submete a prestação completa para aprovação financeira.
     *
     * @throws ValidationException
     */
    public function submitAccountability(
        TravelExpense $te,
        User $actor,
        ?string $note = null
    ): TravelExpense {
        return $this->transition->transitionAccountability(
            $te,
            AccountabilityStatus::SUBMITTED,
            $actor,
            $note ?? 'Prestação enviada para aprovação'
        );
    }

    // ==================================================================
    // INTERNALS
    // ==================================================================

    /**
     * Auto-transição (pending↔in_progress) — não dispara evento, apenas
     * grava history. Usada quando o sistema reage automaticamente a
     * adição/remoção de itens.
     */
    protected function autoTransitionAccountability(
        TravelExpense $te,
        AccountabilityStatus $target,
        User $actor,
        string $note
    ): void {
        $current = $te->accountability_status;
        $te->update([
            'accountability_status' => $target->value,
            'updated_by_user_id' => $actor->id,
        ]);

        TravelExpenseStatusHistory::create([
            'travel_expense_id' => $te->id,
            'kind' => TravelExpenseStatusHistory::KIND_ACCOUNTABILITY,
            'from_status' => $current->value,
            'to_status' => $target->value,
            'changed_by_user_id' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * Acesso a itens é permitido para: criador da verba, beneficiado, e
     * usuários com APPROVE_TRAVEL_EXPENSES, MANAGE_TRAVEL_EXPENSES, ou
     * MANAGE_ACCOUNTABILITY.
     *
     * @throws ValidationException
     */
    protected function ensureCanModifyAccountability(TravelExpense $te, User $actor): void
    {
        $isOwner = $te->created_by_user_id === $actor->id;
        $hasAccountability = $actor->hasPermissionTo(Permission::MANAGE_ACCOUNTABILITY->value);
        $isManager = $actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value);

        if (! $isOwner && ! $hasAccountability && ! $isManager) {
            throw ValidationException::withMessages([
                'accountability' => 'Você não tem permissão para gerenciar itens desta prestação.',
            ]);
        }
    }

    /**
     * Itens só podem ser adicionados/editados/removidos quando:
     *  - verba está APPROVED (não draft, não submitted, não rejected/cancelled/finalized)
     *  - prestação está PENDING ou IN_PROGRESS ou REJECTED (devolvida)
     *
     * Uma vez SUBMITTED, só APPROVE pode mexer (e via "rejeitar" volta a IN_PROGRESS).
     */
    protected function canAddOrEditItems(TravelExpense $te): bool
    {
        if ($te->status !== TravelExpenseStatus::APPROVED) {
            return false;
        }

        return in_array($te->accountability_status, [
            AccountabilityStatus::PENDING,
            AccountabilityStatus::IN_PROGRESS,
            AccountabilityStatus::REJECTED,
        ], true);
    }

    /**
     * @throws ValidationException
     */
    protected function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'attachment' => 'Arquivo de comprovante inválido.',
            ]);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages([
                'attachment' => 'Comprovante excede o tamanho máximo permitido (5MB).',
            ]);
        }

        $mime = $file->getClientMimeType();
        if (! in_array($mime, self::ACCEPTED_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachment' => 'Tipo de arquivo não aceito. Envie PDF, JPG, PNG ou WebP.',
            ]);
        }
    }

    protected function fillAttachmentFields(
        TravelExpenseItem $item,
        UploadedFile $file,
        TravelExpense $te
    ): void {
        $directory = "travel-expenses/{$te->ulid}";
        // store gera nome único automático
        $path = $file->store($directory, 'public');

        $item->attachment_path = $path;
        $item->attachment_original_name = $file->getClientOriginalName();
        $item->attachment_mime = $file->getClientMimeType();
        $item->attachment_size = $file->getSize();
    }

    /**
     * Apaga o arquivo físico do disk. Chamado quando um item é hard-deleted
     * ou quando a verba inteira é apagada (via cascade do controller).
     */
    public function deleteAttachmentFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
