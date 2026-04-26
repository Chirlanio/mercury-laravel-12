<?php

namespace App\Events;

use App\Models\DamagedProduct;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado pelo DamagedProductService::create() após persistência.
 *
 * Consumidores (auto-discovered):
 *  - RunMatchingForNewDamagedProduct: dispara DamagedProductMatchingService
 *    ::findMatchesFor() em sync (rápido — ~1 query por produto candidato)
 *  - NotifyDamagedProductCreated: notifica gerente da loja
 */
class DamagedProductCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DamagedProduct $product,
        public readonly User $actor,
    ) {}
}
