<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checklist extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'store_id',
        'applicator_user_id',
        'status',
        'started_at',
        'completed_at',
        'score_percentage',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'applicator_user_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score_percentage' => 'decimal:2',
    ];

    // Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function applicator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicator_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }

    // Scopes

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    // Business Logic

    public function updateStatusFromAnswers(): void
    {
        $totalAnswers = $this->answers()->count();
        $answeredCount = $this->answers()->where('answer_status', '!=', 'pending')->count();

        if ($answeredCount === 0) {
            $newStatus = 'pending';
        } elseif ($answeredCount < $totalAnswers) {
            $newStatus = 'in_progress';
        } else {
            $newStatus = 'completed';
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === 'in_progress' && !$this->started_at) {
            $updates['started_at'] = now();
        }

        if ($newStatus === 'completed' && !$this->completed_at) {
            $updates['completed_at'] = now();
        }

        // Recalculate score
        $updates['score_percentage'] = $this->calculateScorePercentage();

        $this->update($updates);
    }

    public function calculateScorePercentage(): float
    {
        $answers = $this->answers()->with('question')->get();

        if ($answers->isEmpty()) {
            return 0;
        }

        $maxScore = $answers->sum(fn ($a) => $a->question->points ?? 1);
        $obtainedScore = $answers->sum('score');

        if ($maxScore === 0) {
            return 0;
        }

        return round(($obtainedScore / $maxScore) * 100, 2);
    }

    public function calculateStatistics(): array
    {
        $answers = $this->answers()->with('question.area')->get();
        $totalAnswers = $answers->count();
        $answered = $answers->where('answer_status', '!=', 'pending');

        $distribution = [
            'compliant' => $answers->where('answer_status', 'compliant')->count(),
            'partial' => $answers->where('answer_status', 'partial')->count(),
            'non_compliant' => $answers->where('answer_status', 'non_compliant')->count(),
            'pending' => $answers->where('answer_status', 'pending')->count(),
        ];

        $maxScore = $answers->sum(fn ($a) => $a->question->points ?? 1);
        $obtainedScore = $answers->sum('score');
        $percentage = $maxScore > 0 ? round(($obtainedScore / $maxScore) * 100, 2) : 0;

        // Per-area statistics
        $byArea = $answers->groupBy(fn ($a) => $a->question->area->id ?? 0)->map(function ($areaAnswers) {
            $area = $areaAnswers->first()->question->area;
            $areaMax = $areaAnswers->sum(fn ($a) => $a->question->points ?? 1);
            $areaObtained = $areaAnswers->sum('score');
            $areaPercentage = $areaMax > 0 ? round(($areaObtained / $areaMax) * 100, 2) : 0;

            return [
                'area_id' => $area->id ?? 0,
                'area_name' => $area->name ?? 'Sem Área',
                'max_score' => $areaMax,
                'obtained_score' => round($areaObtained, 2),
                'percentage' => $areaPercentage,
                'total_questions' => $areaAnswers->count(),
                'answered' => $areaAnswers->where('answer_status', '!=', 'pending')->count(),
            ];
        })->values()->all();

        return [
            'total_questions' => $totalAnswers,
            'answered' => $answered->count(),
            'max_score' => $maxScore,
            'obtained_score' => round($obtainedScore, 2),
            'percentage' => $percentage,
            'performance' => self::getPerformanceLabel($percentage),
            'distribution' => $distribution,
            'by_area' => $byArea,
        ];
    }

    public static function getPerformanceLabel(float $percentage): array
    {
        if ($percentage >= 90) {
            return ['label' => 'Excelente', 'color' => 'green'];
        }
        if ($percentage >= 80) {
            return ['label' => 'Muito Bom', 'color' => 'green'];
        }
        if ($percentage >= 70) {
            return ['label' => 'Bom', 'color' => 'blue'];
        }
        if ($percentage >= 60) {
            return ['label' => 'Satisfatório', 'color' => 'yellow'];
        }

        return ['label' => 'Necessita Atenção', 'color' => 'red'];
    }

    public function getDescriptiveAttribute(): string
    {
        $storeName = $this->store->name ?? 'Loja';
        return "Checklist #{$this->id} - {$storeName}";
    }
}
