<?php

namespace App\Models;

use App\Enums\PasswordResetContext;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'context',
        'email',
        'otp_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => PasswordResetContext::class,
            'expires_at' => 'datetime',
        ];
    }
}
