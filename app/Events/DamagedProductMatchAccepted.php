<?php

namespace App\Events;

use App\Models\DamagedProductMatch;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após aceite de um match — Transfer já criada e ambos os
 * produtos transicionados para transfer_requested.
 */
class DamagedProductMatchAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DamagedProductMatch $match,
        public readonly Transfer $transfer,
        public readonly User $actor,
    ) {}
}
