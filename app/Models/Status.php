<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Status extends Model
{
    use HasFactory;

    protected $table = 'statuses';

    protected $fillable = [
        'name',
        'color_theme_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function colorTheme(): BelongsTo
    {
        return $this->belongsTo(ColorTheme::class);
    }

    public static function getOptions(): array
    {
        return static::pluck('name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public static function getActive(): ?self
    {
        return static::where('name', 'Ativo')->first();
    }

    public static function getInactive(): ?self
    {
        return static::where('name', 'Inativo')->first();
    }

    public static function getAnalysis(): ?self
    {
        return static::where('name', 'Analise')->first();
    }

    public function isActive(): bool
    {
        return strtolower($this->name) === 'ativo';
    }

    public function isInactive(): bool
    {
        return strtolower($this->name) === 'inativo';
    }

    public function isAnalysis(): bool
    {
        return strtolower($this->name) === 'analise';
    }

    public function getColorCode(): ?string
    {
        return $this->colorTheme?->color_code;
    }

    public function getColorName(): ?string
    {
        return $this->colorTheme?->color_name;
    }

    public function hasColorTheme(): bool
    {
        return !is_null($this->color_theme_id) && $this->colorTheme()->exists();
    }

    public function changeColorTheme(ColorTheme $colorTheme): bool
    {
        $this->color_theme_id = $colorTheme->id;
        return $this->save();
    }

    public static function getWithColors(): array
    {
        return static::with('colorTheme')
                    ->get()
                    ->mapWithKeys(function ($status) {
                        return [
                            $status->id => [
                                'name' => $status->name,
                                'color_code' => $status->getColorCode(),
                                'color_name' => $status->getColorName(),
                            ]
                        ];
                    })
                    ->toArray();
    }
}
