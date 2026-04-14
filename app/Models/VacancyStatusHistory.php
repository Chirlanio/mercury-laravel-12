<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail de transições de status de vagas. Uma linha por transição,
 * criada pelo VacancyTransitionService. Sem updated_at (registro imutável).
 *
 * Não usa o trait Auditable porque ele próprio é o audit trail — ser
 * auditado levaria a logs redundantes sobre o log.
 */
class VacancyStatusHistory extends Model
{
    protected $table = 'vacancy_status_history';

    public $timestamps = false;

    protected $fillable = [
        'vacancy_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
