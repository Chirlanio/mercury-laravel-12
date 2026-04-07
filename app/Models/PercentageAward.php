<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PercentageAward extends Model
{
    protected $fillable = [
        'level',
        'no_goal_pct',
        'goal_pct',
        'super_goal_pct',
        'hiper_goal_pct',
    ];

    protected $casts = [
        'no_goal_pct' => 'decimal:2',
        'goal_pct' => 'decimal:2',
        'super_goal_pct' => 'decimal:2',
        'hiper_goal_pct' => 'decimal:2',
    ];

    public function getPercentageForTier(string $tier): float
    {
        return match ($tier) {
            'hiper' => (float) $this->hiper_goal_pct,
            'super' => (float) $this->super_goal_pct,
            'goal' => (float) $this->goal_pct,
            default => (float) $this->no_goal_pct,
        };
    }
}
