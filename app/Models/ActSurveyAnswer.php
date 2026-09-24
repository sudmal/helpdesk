<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActSurveyAnswer extends Model
{
    protected $fillable = ['survey_id', 'question_id', 'question_text', 'rating', 'comment'];

    public function survey()
    {
        return $this->belongsTo(ActSurvey::class, 'survey_id');
    }
}
