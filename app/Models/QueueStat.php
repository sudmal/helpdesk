<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueStat extends Model
{
    protected $fillable = ['queue_name', 'waiting', 'talking', 'active_members', 'total_members', 'trunk_status', 'trunk_loss_pct', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
