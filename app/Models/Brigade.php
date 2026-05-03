<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Brigade extends Model
{
    protected $fillable = ['name', 'foreman_id'];

    public function foreman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'foreman_id');
    }

    public function territories(): BelongsToMany
    {
        return $this->belongsToMany(Territory::class, 'brigade_territory');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brigade_user');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Заявки на конкретную дату */
    public function ticketsForDate(\Carbon\Carbon $date): HasMany
    {
        return $this->tickets()
            ->whereDate('scheduled_at', $date)
            ->with(['address', 'type', 'status']);
    }
}
