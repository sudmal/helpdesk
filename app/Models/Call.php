<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = ['phone', 'address_string', 'apartment', 'address_id', 'called_at', 'event', 'payload'];

    protected $casts = [
        'called_at' => 'datetime',
        'payload'   => 'array',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}