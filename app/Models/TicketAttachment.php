<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id', 'comment_id', 'user_id',
        'original_name', 'stored_path', 'mime_type', 'size', 'context',
    ];

    public function ticket(): BelongsTo  { return $this->belongsTo(Ticket::class); }
    public function comment(): BelongsTo { return $this->belongsTo(TicketComment::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->stored_path);
    }

    public function isImage(): bool { return str_starts_with($this->mime_type, 'image/'); }
    public function isVideo(): bool { return str_starts_with($this->mime_type, 'video/'); }
    public function isAudio(): bool { return str_starts_with($this->mime_type, 'audio/'); }

    /** Разрешённые MIME-типы */
    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/avi',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/m4a',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
}
