<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['extension', 'status', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
