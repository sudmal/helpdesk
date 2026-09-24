<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActSurvey extends Model
{
    protected $fillable = [
        'act_id', 'status', 'attempts', 'last_attempt_at', 'last_attempt_note',
        'overall_comment', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function act()
    {
        return $this->belongsTo(Act::class);
    }

    public function answers()
    {
        return $this->hasMany(ActSurveyAnswer::class, 'survey_id');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
