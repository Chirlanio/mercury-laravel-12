<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageStatus extends Model
{
    use HasFactory;

    protected $table = 'page_statuses';

    protected $fillable = [
        'name',
        'color',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
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

    public function isSuccessColor(): bool
    {
        return $this->color === 'success';
    }

    public function isDangerColor(): bool
    {
        return $this->color === 'danger';
    }

    public function isPrimaryColor(): bool
    {
        return $this->color === 'primary';
    }

    public function getBootstrapClass(): string
    {
        return "badge bg-{$this->color}";
    }

    public function getButtonClass(): string
    {
        return "btn btn-{$this->color}";
    }

    public function getTextClass(): string
    {
        return "text-{$this->color}";
    }

    public function getPagesCount(): int
    {
        return $this->pages()->count();
    }

    public static function getWithCounts(): array
    {
        return static::withCount('pages')
                    ->get()
                    ->mapWithKeys(function ($pageStatus) {
                        return [
                            $pageStatus->id => [
                                'name' => $pageStatus->name,
                                'color' => $pageStatus->color,
                                'pages_count' => $pageStatus->pages_count,
                                'bootstrap_class' => $pageStatus->getBootstrapClass(),
                            ]
                        ];
                    })
                    ->toArray();
    }

    public static function getColorMapping(): array
    {
        return [
            'success' => 'Sucesso/Ativo',
            'danger' => 'Erro/Inativo',
            'primary' => 'Principal/Análise',
            'warning' => 'Aviso',
            'info' => 'Informação',
            'secondary' => 'Secundário',
        ];
    }
}
