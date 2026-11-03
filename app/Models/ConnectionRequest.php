<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'name', 'phone', 'address_string', 'description',
        'status', 'scheduled_at', 'notes', 'act_number',
        'assigned_to', 'created_by', 'source', 'territory_id', 'brigade_id', 'service_type_id', 'kind', 'needs_callback',
        'feasibility', 'feasibility_comment', 'feasibility_by', 'feasibility_at',
    ];

    protected $casts = [
        'scheduled_at'   => 'datetime',
        'needs_callback' => 'boolean',
        'feasibility_at' => 'datetime',
    ];

    // last_comment (2026-09-15) — виден на фронте автоматически только там,
    // где logs явно eager-loaded (see getLastCommentAttribute()); в остальных
    // местах просто null, лишней нагрузки не добавляет.
    protected $appends = ['last_comment'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function feasibilityByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feasibility_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function brigade(): BelongsTo
    {
        return $this->belongsTo(Brigade::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function act(): HasOne
    {
        return $this->hasOne(Act::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ConnectionRequestMaterial::class);
    }

    public function logs(): HasMany
    {
        // latest() сортирует только по created_at (секундная точность) — две
        // записи лога в одну секунду иначе оказываются в непредсказуемом
        // порядке; id как тай-брейкер даёт стабильный порядок.
        return $this->hasMany(ConnectionRequestLog::class)->latest()->orderByDesc('id');
    }

    /**
     * Последний непустой комментарий из истории (2026-09-15) — для тултипов
     * в Календаре/Дашборде, где раньше по ошибке показывалось имя абонента
     * вместо реально полезного комментария (например, оставленного при
     * назначении даты — "Прозвонить заранее"). Работает только если
     * relation logs уже eager-loaded (with('logs')) — иначе просто null,
     * НЕ триггерит ленивую подгрузку (N+1 при массовой сериализации
     * списков в Календаре/Дашборде).
     */
    public function getLastCommentAttribute(): ?string
    {
        if (!$this->relationLoaded('logs')) {
            return null;
        }
        return $this->logs->first(fn($l) => filled($l->notes))?->notes;
    }

    /**
     * Присвоить заявке номер (<in|cn>-YYMMDDNN, схема Act::generateNumberForConnectionRequest())
     * один раз, при первом назначении даты подключения/переключения на PON —
     * см. ConnectionRequestController::update(). Монтажник получает номер уже
     * на руках до выезда и может сразу писать его на бумажный акт, не дожидаясь
     * закрытия заявки. При закрытии этот же номер становится номером самого
     * Act (см. close()/addAct() в обоих контроллерах) — тот же принцип, что и
     * Ticket::number -> Act::number у обычных заявок.
     *
     * Вызывающий код сам решает, вызывать ли этот метод внутри транзакции —
     * здесь это не требуется: retry на дубликате номера (как в
     * Act::createWithGeneratedNumber()) покрывает редкую гонку без блокировки
     * на уровне вызова, а nextNumberForPrefix() внутри Act уже лочит нужные
     * строки сама.
     */
    public function assignNumber(int $maxAttempts = 5): string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $number = Act::generateNumberForConnectionRequest($this);
            try {
                $this->update(['number' => $number]);
                return $number;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23000' || $attempt === $maxAttempts) {
                    throw $e;
                }
                // Коллизия по уникальному number — конкурент забронировал этот
                // номер первым между нашим чтением и записью. Пробуем следующий.
            }
        }
    }
}
