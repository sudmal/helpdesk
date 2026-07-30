<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrigadeScheduleLog extends Model
{
    protected $fillable = ['brigade_id', 'user_id', 'action', 'description', 'changes'];

    protected $casts = ['changes' => 'array'];

    public function brigade() { return $this->belongsTo(Brigade::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
