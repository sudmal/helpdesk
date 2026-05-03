<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationsLog extends Model
{
    protected $table = 'notifications_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'channel', 'type', 'ticket_id', 'payload', 'success', 'error', 'sent_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
}
