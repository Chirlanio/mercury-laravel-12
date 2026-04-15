<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lookup de EAN-13 por (reference, size). Idempotente — uma combinação
 * sempre tem o mesmo barcode.
 */
class PurchaseOrderBarcode extends Model
{
    protected $fillable = [
        'reference',
        'size',
        'barcode',
    ];
}
