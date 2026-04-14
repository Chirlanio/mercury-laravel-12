<?php

namespace App\Models;

use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vaga aberta (paridade v1 — adms_vacancy_opening).
 *
 * State machine em VacancyStatus. Transições aplicadas via
 * VacancyTransitionService (não mutar status diretamente nos callers).
 *
 * Integração com PersonnelMovement:
 *  - origin_movement_id: desligamento que originou a vaga de substituição
 *    (preenchido pelo CreateSubstitutionVacancyFromDismissal listener).
 *  - hired_employee_id: funcionário criado como pré-cadastro (status=Pendente)
 *    quando a vaga é finalizada. NÃO é um PersonnelMovement de admissão.
 *
 * Soft delete segue a convenção de PersonnelMovement: coluna deleted_at
 * manipulada manualmente pelo service, sem trait SoftDeletes.
 */
class Vacancy extends Model
{
    use Auditable;

    protected $fillable = [
        'store_id',
        'position_id',
        'work_schedule_id',
        'request_type',
        'replaced_employee_id',
        'origin_movement_id',
        'status',
        'recruiter_id',
        'predicted_sla_days',
        'effective_sla_days',
        'delivery_forecast',
        'closing_date',
        'hired_employee_id',
        'date_admission',
        'interview_hr',
        'evaluators_hr',
        'interview_leader',
        'evaluators_leader',
        'comments',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'request_type' => VacancyRequestType::class,
        'status' => VacancyStatus::class,
        'delivery_forecast' => 'date',
        'closing_date' => 'date',
        'date_admission' => 'date',
        'deleted_at' => 'datetime',
        'predicted_sla_days' => 'integer',
        'effective_sla_days' => 'integer',
    ];

    // ------------------------------------------------------------------
    // State machine helpers (delegam para o enum)
    // ------------------------------------------------------------------

    public function canTransitionTo(VacancyStatus|string $target): bool
    {
        $target = $target instanceof VacancyStatus ? $target : VacancyStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    /**
     * Atalho para exibir no frontend sem precisar recarregar o enum cast.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getRequestTypeLabelAttribute(): string
    {
        return $this->request_type->label();
    }

    /**
     * Vaga está "vencida" se o prazo previsto passou e a vaga ainda não
     * entrou em estado terminal.
     */
    public function isOverdue(): bool
    {
        if ($this->isTerminal() || ! $this->delivery_forecast) {
            return false;
        }

        return $this->delivery_forecast->isPast();
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_id', $storeCode);
    }

    public function scopeForStatus(Builder $query, VacancyStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof VacancyStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (VacancyStatus $s) => $s->value,
            VacancyStatus::active()
        ));
    }

    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (VacancyStatus $s) => $s->value,
            VacancyStatus::terminal()
        ));
    }

    /**
     * Vagas ativas com delivery_forecast < hoje.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('delivery_forecast')
            ->whereDate('delivery_forecast', '<', now()->toDateString());
    }

    /**
     * Exclui registros soft-deleted (sem usar SoftDeletes trait — segue
     * convenção manual do projeto).
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function replacedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replaced_employee_id');
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    public function originMovement(): BelongsTo
    {
        return $this->belongsTo(PersonnelMovement::class, 'origin_movement_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(VacancyStatusHistory::class)->orderByDesc('created_at');
    }
}
