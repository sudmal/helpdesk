<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrigadeSchedule extends Model
{
    protected $fillable = ['brigade_id', 'user_id', 'date', 'status'];

    protected $casts = ['date' => 'date'];

    public function brigade() { return $this->belongsTo(Brigade::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
