<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Builder;

class Ticket extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::observe(\App\Observers\TicketObserver::class);
    }

    protected $fillable = [
        'number', 'address_id', 'type_id', 'status_id', 'brigade_id',
        'created_by', 'assigned_to', 'description', 'phone', 'contract_no',
        'priority', 'scheduled_at', 'started_at', 'paused_at', 'closed_at',
        'close_notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'paused_at'    => 'datetime',
        'closed_at'    => 'datetime',
    ];

    // === Relations ===
    public function address(): BelongsTo   { return $this->belongsTo(Address::class); }
    public function type(): BelongsTo      { return $this->belongsTo(TicketType::class); }
    public function status(): BelongsTo    { return $this->belongsTo(TicketStatus::class); }
    public function brigade(): BelongsTo   { return $this->belongsTo(Brigade::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments(): HasMany    { return $this->hasMany(TicketComment::class)->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class); }
    public function history(): HasMany     { return $this->hasMany(TicketHistory::class)->latest(); }

    // === Scopes ===
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('number', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('contract_no', 'like', "%{$term}%")
              ->orWhereHas('address', fn($a) => $a->search($term))
              ->orWhereHas('brigade', fn($b) => $b->where('name', 'like', "%{$term}%"));
        });
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('scheduled_at', $date);
    }

    public function scopeForBrigade(Builder $query, int $brigadeId): Builder
    {
        return $query->where('brigade_id', $brigadeId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn($s) => $s->where('is_final', false));
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    // === Helpers ===
    public static function generateNumber(): string
    {
        $last = static::withTrashed()->latest('id')->value('id') ?? 0;
        return 'Т-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }

    public function isClosed(): bool
    {
        return (bool) $this->closed_at;
    }
}
