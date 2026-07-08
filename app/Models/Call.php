<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = ['phone', 'address_string', 'apartment', 'address_id', 'lanbilling_uid', 'lanbilling_name', 'lanbilling_blocked', 'called_at', 'event', 'payload', 'queue_status', 'operator_ext', 'wait_seconds'];

    protected $casts = [
        'called_at'          => 'datetime',
        'payload'            => 'array',
        'lanbilling_blocked' => 'integer',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}