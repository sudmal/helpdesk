<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyTarget extends Model
{
    protected $fillable = ['ticket_type_id', 'connection_kind'];
}
