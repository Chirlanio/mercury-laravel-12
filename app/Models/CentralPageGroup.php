<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralPageGroup extends Model
{
    protected $fillable = ['name'];

    public function pages()
    {
        return $this->hasMany(CentralPage::class);
    }
}
