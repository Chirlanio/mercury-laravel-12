<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralMenuPageDefault extends Model
{
    protected $fillable = [
        'central_menu_id',
        'central_page_id',
        'role_slug',
        'permission',
        'order',
        'dropdown',
        'lib_menu',
    ];

    protected $casts = [
        'permission' => 'boolean',
        'order' => 'integer',
        'dropdown' => 'boolean',
        'lib_menu' => 'boolean',
    ];

    public function menu()
    {
        return $this->belongsTo(CentralMenu::class, 'central_menu_id');
    }

    public function page()
    {
        return $this->belongsTo(CentralPage::class, 'central_page_id');
    }
}
