<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'ip', 'login', 'password_attempt', 'method',
        'success', 'was_blocked', 'caused_block',
    ];

    protected $casts = [
        'success'      => 'boolean',
        'was_blocked'  => 'boolean',
        'caused_block' => 'boolean',
    ];
}