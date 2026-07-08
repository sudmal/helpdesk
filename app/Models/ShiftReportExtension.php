<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftReportExtension extends Model
{
    protected $fillable = [
        'shift_report_id', 'extension',
        'seconds_dnd', 'seconds_offline', 'seconds_idle', 'seconds_in_call',
        'calls_answered', 'call_duration_min_sec', 'call_duration_avg_sec', 'call_duration_max_sec',
        'unique_numbers',
    ];

    protected $casts = [
        'call_duration_avg_sec' => 'float',
    ];

    public function shiftReport()
    {
        return $this->belongsTo(ShiftReport::class);
    }
}
