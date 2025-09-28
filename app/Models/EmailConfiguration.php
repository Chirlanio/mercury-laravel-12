<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailConfiguration extends Model
{
    use HasFactory;

    protected $table = 'email_configurations';

    protected $fillable = [
        'name',
        'email',
        'host',
        'username',
        'password',
        'smtp_security',
        'port',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'port' => 'integer',
    ];

    /**
     * Get the default email configuration
     */
    public static function getDefault()
    {
        return static::first();
    }

    /**
     * Get SMTP configuration array for Laravel Mail
     */
    public function getSmtpConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->smtp_security,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => null,
        ];
    }
}
