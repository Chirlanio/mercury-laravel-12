<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'date',
        'type',
        'is_recurring',
        'year',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where(function ($q) use ($year) {
            $q->where('is_recurring', true)
                ->orWhere('year', $year);
        });
    }

    /**
     * Retorna todas as datas de feriado para um ano, ajustando recorrentes.
     */
    public static function getDatesForYear(int $year): array
    {
        $holidays = static::active()->forYear($year)->get();
        $dates = [];

        foreach ($holidays as $holiday) {
            if ($holiday->is_recurring) {
                $dates[] = $holiday->date->setYear($year)->format('Y-m-d');
            } else {
                $dates[] = $holiday->date->format('Y-m-d');
            }
        }

        return $dates;
    }

    /**
     * Verifica se uma data é feriado.
     */
    public static function isHoliday(string $date): bool
    {
        $year = (int) date('Y', strtotime($date));

        return in_array($date, static::getDatesForYear($year));
    }
}
