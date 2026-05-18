<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = ['ip', 'auto_blocked', 'blocked_until', 'unblocked_at'];

    protected $casts = [
        'auto_blocked' => 'boolean',
        'blocked_until' => 'datetime',
        'unblocked_at'  => 'datetime',
    ];
}