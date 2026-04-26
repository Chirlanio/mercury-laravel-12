<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando a engine cria um novo match (status=pending).
 *
 * Consumidor padrão: NotifyDamagedProductMatchFound — notifica os gerentes
 * das duas lojas envolvidas pra que aceitem ou rejeitem.
 */
class DamagedProductMatchFound
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DamagedProductMatch $match,
    ) {}
}
