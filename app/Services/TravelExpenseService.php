<?php

namespace App\Services;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de verbas de viagem. Não manipula status além do estado inicial DRAFT
 * (com auto-submit opcional). Todas as transições subsequentes vão pelo
 * TravelExpenseTransitionService.
 *
 * Responsável por:
 *  - Calcular value = daily_rate * days_count
 *  - Validar pagamento (bank XOR pix — pelo menos um quando submit)
 *  - Validar datas (end_date >= initial_date)
 *  - Resolver daily_rate da policy (default 100.00, pode ser por
 *    employee.position.level no futuro)
 *  - Soft-delete com bloqueio se já tiver itens lançados
 */
class TravelExpenseService
{
    /**
     * Taxa diária default (R$/dia). Pode ser sobrescrita via:
     *  - Argumento explícito no create/update
     *  - config('travel_expenses.daily_rate') (futuro)
     *  - Policy por position level (Fase 2.5+ futura)
     */
    public const DEFAULT_DAILY_RATE = 100.00;

    public function __construct(
        protected TravelExpenseTransitionService $transition,
    ) {}

    /**
     * Cria uma verba em estado DRAFT. Se $autoSubmit=true, transiciona pra
     * SUBMITTED (envia pra aprovação).
     *
     * Campos esperados em $data:
     *  - employee_id (int, required)
     *  - store_code (string, required)
     *  - origin / destination (string, required)
     *  - initial_date / end_date (Y-m-d, required)
     *  - description (text, required)
     *  - cpf (opcional, string — encriptado e hashed automaticamente)
     *  - daily_rate (opcional, decimal — default DEFAULT_DAILY_RATE)
     *  - bank_id + bank_branch + bank_account (opcional, mas obrigatório XOR pix)
     *  - pix_type_id + pix_key (opcional, mas obrigatório XOR bank)
     *  - client_name, internal_notes (opcional)
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor, bool $autoSubmit = false): TravelExpense
    {
        $this->validateDates($data['initial_date'] ?? null, $data['end_date'] ?? null);

        $dailyRate = isset($data['daily_rate'])
            ? (float) $data['daily_rate']
            : self::DEFAULT_DAILY_RATE;

        $daysCount = $this->calculateDays($data['initial_date'], $data['end_date']);
        $value = round($dailyRate * $daysCount, 2);

        $te = DB::transaction(function () use ($data, $actor, $dailyRate, $daysCount, $value) {
            $te = new TravelExpense();
            $te->fill([
                'employee_id' => $data['employee_id'],
                'store_code' => $data['store_code'],
                'origin' => $data['origin'],
                'destination' => $data['destination'],
                'initial_date' => $data['initial_date'],
                'end_date' => $data['end_date'],
                'daily_rate' => $dailyRate,
                'days_count' => $daysCount,
                'value' => $value,
                'client_name' => $data['client_name'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'pix_type_id' => $data['pix_type_id'] ?? null,
                'description' => $data['description'],
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            // CPF e PIX key vão por mutator (encryption + hash)
            if (! empty($data['cpf'])) {
                $te->cpf = $data['cpf'];
            }
            if (! empty($data['pix_key'])) {
                $te->pix_key = $data['pix_key'];
            }

            $te->save();

            // History inicial
            TravelExpenseStatusHistory::create([
                'travel_expense_id' => $te->id,
                'kind' => TravelExpenseStatusHistory::KIND_EXPENSE,
                'from_status' => null,
                'to_status' => TravelExpenseStatus::DRAFT->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Verba criada',
                'created_at' => now(),
            ]);

            return $te;
        });

        if ($autoSubmit) {
            $te = $this->transition->transitionExpense(
                $te,
                TravelExpenseStatus::SUBMITTED,
                $actor,
                'Solicitação enviada automaticamente após criação'
            );
        }

        return $te->fresh(['employee', 'store', 'bank', 'pixType', 'statusHistory', 'createdBy']);
    }

    /**
     * Atualiza uma verba. Edição permitida apenas em DRAFT/SUBMITTED para
     * usuários sem MANAGE_TRAVEL_EXPENSES. Em estados aprovados/finalizados
     * só MANAGE pode editar (e ainda assim apenas notas internas).
     *
     * @throws ValidationException
     */
    public function update(TravelExpense $te, array $data, User $actor): TravelExpense
    {
        if ($te->is_deleted) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Não é possível editar uma verba excluída.',
            ]);
        }

        $isManager = $actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value);
        $editableStatuses = [TravelExpenseStatus::DRAFT, TravelExpenseStatus::SUBMITTED];

        if (! in_array($te->status, $editableStatuses, true) && ! $isManager) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Verba só pode ser editada enquanto estiver em Rascunho ou Solicitada.',
            ]);
        }

        // Em estados pós-aprovação, só notas internas + descrição são editáveis
        // mesmo com MANAGE (proteção de auditoria).
        if (! in_array($te->status, $editableStatuses, true)) {
            $allowedFields = ['internal_notes', 'description'];
            $data = array_intersect_key($data, array_flip($allowedFields));
        }

        // Recalcular valor se datas/rate mudaram
        $datesChanged = (isset($data['initial_date']) && $data['initial_date'] !== $te->initial_date->format('Y-m-d'))
            || (isset($data['end_date']) && $data['end_date'] !== $te->end_date->format('Y-m-d'));
        $rateChanged = isset($data['daily_rate']) && (float) $data['daily_rate'] !== (float) $te->daily_rate;

        if ($datesChanged || $rateChanged) {
            $initial = $data['initial_date'] ?? $te->initial_date->format('Y-m-d');
            $end = $data['end_date'] ?? $te->end_date->format('Y-m-d');
            $this->validateDates($initial, $end);

            $rate = isset($data['daily_rate']) ? (float) $data['daily_rate'] : (float) $te->daily_rate;
            $days = $this->calculateDays($initial, $end);

            $data['days_count'] = $days;
            $data['value'] = round($rate * $days, 2);
        }

        return DB::transaction(function () use ($te, $data, $actor) {
            // Mutators
            if (array_key_exists('cpf', $data)) {
                $te->cpf = $data['cpf'];
                unset($data['cpf']);
            }
            if (array_key_exists('pix_key', $data)) {
                $te->pix_key = $data['pix_key'];
                unset($data['pix_key']);
            }

            $data['updated_by_user_id'] = $actor->id;
            $te->fill($data);
            $te->save();

            return $te->fresh(['employee', 'store', 'bank', 'pixType', 'statusHistory', 'items']);
        });
    }

    /**
     * Soft-delete. Bloqueado se já tiver itens lançados ou se status for
     * APPROVED/FINALIZED (auditoria financeira).
     *
     * @throws ValidationException
     */
    public function delete(TravelExpense $te, User $actor, ?string $reason = null): void
    {
        if ($te->is_deleted) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Verba já está excluída.',
            ]);
        }

        if (in_array($te->status, [TravelExpenseStatus::APPROVED, TravelExpenseStatus::FINALIZED], true)) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Não é possível excluir verba aprovada/finalizada. Cancele primeiro.',
            ]);
        }

        if ($te->items()->exists()) {
            throw ValidationException::withMessages([
                'travel_expense' => 'Não é possível excluir verba com itens de prestação lançados. Remova os itens primeiro.',
            ]);
        }

        $te->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);
    }

    /**
     * Valida que pelo menos UMA forma de pagamento está completa, aceitando
     * ambas. Usado pelo TransitionService antes de DRAFT → SUBMITTED.
     *
     * @throws ValidationException
     */
    public function ensurePaymentInfo(TravelExpense $te): void
    {
        $hasBank = $te->bank_id && $te->bank_branch && $te->bank_account;
        $hasPix = $te->pix_type_id && $te->pix_key;

        if (! $hasBank && ! $hasPix) {
            throw ValidationException::withMessages([
                'payment' => 'Informe ao menos uma forma de pagamento (dados bancários completos OU chave PIX).',
            ]);
        }
    }

    /**
     * Conta dias inclusivamente (ida e volta no mesmo dia = 1; ida segunda
     * volta sexta = 5).
     */
    public function calculateDays(string $initialDate, string $endDate): int
    {
        $start = Carbon::parse($initialDate);
        $end = Carbon::parse($endDate);

        // diffInDays é exclusivo do dia inicial; +1 para incluir
        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * @throws ValidationException
     */
    public function validateDates(?string $initialDate, ?string $endDate): void
    {
        if (empty($initialDate) || empty($endDate)) {
            throw ValidationException::withMessages([
                'dates' => 'Datas de saída e retorno são obrigatórias.',
            ]);
        }

        $start = Carbon::parse($initialDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'Data de retorno não pode ser anterior à data de saída.',
            ]);
        }
    }

    /**
     * Resolve o store_code de scoping pra um usuário. Retorna null pra
     * usuários com MANAGE_TRAVEL_EXPENSES (vêem todas as lojas).
     * Padrão idêntico aos demais módulos: user->store_id é o código da
     * loja primária do usuário (ex: "Z421").
     */
    public function resolveScopedStoreCode(User $actor): ?string
    {
        if ($actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value)) {
            return null;
        }

        return $actor->store_id ?: null;
    }

    /**
     * Aplica scope de visibilidade. Sem MANAGE, filtra por store_code do
     * usuário. APROVE_TRAVEL_EXPENSES (Financeiro) também vê tudo, pq
     * precisa aprovar verbas de qualquer loja.
     */
    public function scopedQuery(User $actor)
    {
        $query = TravelExpense::query()->notDeleted();

        if ($actor->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value)
            || $actor->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value)) {
            return $query;
        }

        $storeCode = $actor->store_id ?: null;
        if ($storeCode) {
            return $query->forStore($storeCode);
        }

        // Usuário sem store_code só vê suas próprias solicitações
        return $query->where('created_by_user_id', $actor->id);
    }
}
