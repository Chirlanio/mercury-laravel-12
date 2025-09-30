<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailSetting extends Model
{
    protected $fillable = [
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'timeout',
        'from_address',
        'from_name',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
        'timeout' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the active email settings
     */
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Encrypt password before saving
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt password when retrieving
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Check if password is set
     */
    public function hasPassword(): bool
    {
        return !empty($this->attributes['password']);
    }
}
