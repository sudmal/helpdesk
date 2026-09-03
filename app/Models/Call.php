<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = ['phone', 'address_string', 'apartment', 'address_id', 'lanbilling_uid', 'lanbilling_name', 'lanbilling_blocked', 'session_online', 'session_ip', 'session_redirect', 'called_at', 'event', 'payload', 'queue_status', 'operator_ext', 'wait_seconds'];

    protected $casts = [
        'called_at'          => 'datetime',
        'payload'            => 'array',
        'lanbilling_blocked' => 'integer',
        'session_online'     => 'boolean',
        'session_redirect'   => 'boolean',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}