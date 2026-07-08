<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftReport extends Model
{
    protected $fillable = [
        'shift_definition_id', 'shift_date', 'shift_name', 'shift_start_at', 'shift_end_at',
        'total_calls', 'answered_calls', 'missed_calls', 'missed_percent',
        'avg_wait_sec', 'max_wait_sec', 'sla_threshold_sec', 'sla_percent',
        'unique_numbers', 'generated_at',
    ];

    protected $casts = [
        'shift_date'     => 'date',
        'shift_start_at' => 'datetime',
        'shift_end_at'   => 'datetime',
        'generated_at'   => 'datetime',
        'missed_percent' => 'float',
        'avg_wait_sec'   => 'float',
        'sla_percent'    => 'float',
    ];

    public function extensions()
    {
        return $this->hasMany(ShiftReportExtension::class);
    }
}
