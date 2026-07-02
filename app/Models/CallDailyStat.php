<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallDailyStat extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'stat_date', 'hour',
        'total_calls', 'answered', 'missed',
        'avg_wait_sec', 'max_wait_sec',
        'max_queue_depth', 'avg_queue_depth', 'avg_operators',
    ];

    protected $casts = ['stat_date' => 'date'];
}
