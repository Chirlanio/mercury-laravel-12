<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DismissalFollowUp extends Model
{
    protected $fillable = [
        'personnel_movement_id',
        'employee_id',
        'uniform',
        'phone_chip',
        'original_card',
        'aso',
        'aso_resigns',
        'send_aso_guide',
        'signature_date_trct',
        'termination_date',
    ];

    protected $casts = [
        'uniform' => 'boolean',
        'phone_chip' => 'boolean',
        'original_card' => 'boolean',
        'aso' => 'boolean',
        'aso_resigns' => 'boolean',
        'send_aso_guide' => 'boolean',
        'signature_date_trct' => 'date',
        'termination_date' => 'date',
    ];

    public function personnelMovement(): BelongsTo
    {
        return $this->belongsTo(PersonnelMovement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
