<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActHistory extends Model
{
    protected $table = 'act_history';

    protected $fillable = ['act_id', 'user_id', 'action', 'field', 'old_value', 'new_value'];

    public function act(): BelongsTo  { return $this->belongsTo(Act::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
