<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Brigade extends Model
{
    protected $fillable = ['name', 'foreman_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function foreman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'foreman_id');
    }

    public function territories(): BelongsToMany
    {
        return $this->belongsToMany(Territory::class, 'brigade_territory');
    }

    /**
     * Участки обслуживания (Интернет/КТВ/Прочее и т.п.) -- в отличие от
     * territories, пустой список здесь означает "обслуживает все участки",
     * а не "не обслуживает ничего" (см. комментарий в миграции
     * create_brigade_service_type_table). Так старые бригады, у которых
     * это поле ещё не настроено, не исчезают из списков назначения при
     * создании заявки -- ограничение появляется только когда админ явно
     * отметил конкретные участки в Настройках бригад.
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'brigade_service_type');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brigade_user')
            ->withPivot('exclude_from_schedule');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function connectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class);
    }

    /** Заявки на конкретную дату */
    public function ticketsForDate(\Carbon\Carbon $date): HasMany
    {
        return $this->tickets()
            ->whereDate('scheduled_at', $date)
            ->with(['address', 'type', 'status']);
    }
}
