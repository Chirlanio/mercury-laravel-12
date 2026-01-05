<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ColorTheme extends Model
{
    use HasFactory;

    protected $table = 'color_themes';

    protected $fillable = [
        'name',
        'color_class',
        'hex_color',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all color themes as key-value pairs (id => name)
     */
    public static function getOptions(): array
    {
        return static::pluck('name', 'id')->toArray();
    }

    /**
     * Get all color themes as key-value pairs (color_class => name)
     */
    public static function getColorClassOptions(): array
    {
        return static::pluck('name', 'color_class')->toArray();
    }

    /**
     * Get a color theme by its color class
     */
    public static function getByColorClass(string $colorClass): ?self
    {
        return static::where('color_class', $colorClass)->first();
    }

    /**
     * Get Bootstrap/CSS class for this color theme
     */
    public function getBootstrapClass(string $prefix = 'btn-'): string
    {
        return $prefix . $this->color_class;
    }

    /**
     * Get all available Bootstrap color classes
     */
    public static function getBootstrapColors(): array
    {
        return [
            'primary' => 'Azul',
            'secondary' => 'Cinza',
            'success' => 'Verde',
            'danger' => 'Vermelho',
            'warning' => 'Laranja',
            'info' => 'Azul Claro',
            'light' => 'Branco',
            'dark' => 'Preto',
        ];
    }
}
