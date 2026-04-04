<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_id',
        'checklist_question_id',
        'answer_status',
        'score',
        'justification',
        'action_plan',
        'responsible_employee_id',
        'deadline_date',
    ];

    protected $casts = [
        'checklist_id' => 'integer',
        'checklist_question_id' => 'integer',
        'score' => 'decimal:2',
        'responsible_employee_id' => 'integer',
        'deadline_date' => 'date',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function calculateScore(): float
    {
        $points = $this->question->points ?? 1;

        return match ($this->answer_status) {
            'compliant' => (float) $points * 1.0,
            'partial' => (float) $points * 0.5,
            'non_compliant' => 0.0,
            default => 0.0,
        };
    }
}
