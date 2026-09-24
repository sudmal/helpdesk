<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    protected $fillable = ['text', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function answers()
    {
        return $this->hasMany(ActSurveyAnswer::class, 'question_id');
    }
}
