<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\Builder;

class Ticket extends Model
{
    use SoftDeletes;
protected $fillable = [
        'number', 'address_id', 'apartment', 'type_id', 'service_type_id', 'status_id', 'brigade_id',
        'created_by', 'assigned_to', 'description', 'phone', 'contract_no',
        'priority', 'scheduled_at', 'started_at', 'paused_at', 'closed_at',
        'close_notes', 'act_number', 'closed_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'paused_at'    => 'datetime',
        'closed_at'    => 'datetime',
    ];

    // Полный адрес заявки -- как у Address::full_address, но квартира
    // берётся В ПЕРВУЮ ОЧЕРЕДЬ из самой заявки (tickets.apartment), и только
    // если там пусто -- из связанного адреса. Нужно потому что заявка может
    // ссылаться на адрес уровня дома/подъезда (там apartment=NULL -- нет
    // отдельной именной записи для каждой квартиры), а номер квартиры при
    // этом введён/пришёл прямо в саму заявку. Раньше в актах (печать/карточка/
    // список/мобильный API) использовался только Address::full_address --
    // квартира в таком случае молча пропадала из акта, хотя на самой заявке
    // была видна (см. Tickets/Show.vue, там точно такой же fallback).
    public function getFullAddressAttribute(): string
    {
        $apartment = $this->apartment ?: $this->address?->apartment;
        $parts = array_filter([
            $this->address?->city,
            $this->address?->street,
            $this->address?->building,
            $apartment ? 'кв. ' . $apartment : null,
        ]);
        return implode(', ', $parts);
    }

    // === Relations ===
    public function address(): BelongsTo      { return $this->belongsTo(Address::class); }
    public function serviceType(): BelongsTo  { return $this->belongsTo(\App\Models\ServiceType::class, 'service_type_id'); }
    public function type(): BelongsTo      { return $this->belongsTo(TicketType::class); }
    public function status(): BelongsTo    { return $this->belongsTo(TicketStatus::class); }
    public function brigade(): BelongsTo   { return $this->belongsTo(Brigade::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function closedBy(): BelongsTo  { return $this->belongsTo(User::class, 'closed_by'); }
    public function comments(): HasMany    { return $this->hasMany(TicketComment::class)->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class); }
        public function materials(): HasMany
    {
        return $this->hasMany(TicketMaterial::class);
    }

    public function history(): HasMany     { return $this->hasMany(TicketHistory::class)->latest(); }
    public function act(): HasOne          { return $this->hasOne(Act::class); }

    // === Scopes ===
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // Парсим запрос: выделяем текст, дом и квартиру
        $words        = array_values(array_filter(explode(' ', trim($term))));
        $textWords    = [];
        $buildingHint = null;
        $aptHint      = null;

        foreach ($words as $word) {
            if (preg_match('/^\d+[а-яёa-z]?$/iu', $word)) {
                if ($buildingHint === null) $buildingHint = $word;
                else $aptHint = $word;
            } else {
                $textWords[] = $word;
            }
        }

        $streetTerm = implode(' ', $textWords);

        return $query->where(function ($q) use ($term, $streetTerm, $buildingHint, $aptHint) {
            $q->where('number', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('contract_no', 'like', "%{$term}%");

            // Поиск по адресу с разбором улица+дом+квартира
            if ($buildingHint && $aptHint) {
                // Улица + дом + квартира — все три условия вместе
                $q->orWhere(function ($sub) use ($streetTerm, $buildingHint, $aptHint) {
                    $sub->where('apartment', $aptHint)
                        ->whereHas('address', function ($a) use ($streetTerm, $buildingHint) {
                            if ($streetTerm) $a->search($streetTerm);
                            $a->where('building', $buildingHint);
                        });
                });
            } elseif ($buildingHint) {
                // Улица + дом (без квартиры)
                $q->orWhereHas('address', function ($a) use ($streetTerm, $buildingHint) {
                    if ($streetTerm) $a->search($streetTerm);
                    $a->where('building', $buildingHint);
                });
            } else {
                $q->orWhereHas('address', fn($a) => $a->search($term));
            }

            $q->orWhereHas('brigade', fn($b) => $b->where('name', 'like', "%{$term}%"));
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

    /**
     * Номер заявки (2026-09-12): <буква участка>-YYMMDDNNN — буква как и
     * раньше (i/c/Т по участку), дальше ДАТА (6 знаков) + порядковый номер
     * ЗА ЭТОТ ДЕНЬ в рамках этой буквы (3 знака, свой счётчик на каждый
     * день и каждую букву — обнуляется каждые сутки). Раньше номер был
     * просто сквозным счётчиком без даты (например "i-022722") — старые
     * номера НЕ переименовываются, продолжают существовать как есть,
     * новая схема действует только для новых номеров.
     *
     * Формат сознательно копирует ту же идею, что уже была у номеров
     * актов (см. Act::nextNumberForPrefix()) — акт для обычной заявки
     * теперь СВОЙ номер не генерирует вовсе, а берёт готовый номер заявки
     * (см. TicketController::close()) — вот ради чего вся эта миграция и
     * затевалась: монтажник видит номер сразу при назначении заявки, не
     * дожидаясь закрытия. Буква типа акта (ремонт/обычный) из номера
     * убрана целиком — тип остаётся обычным полем на самом акте, просто
     * больше не кодируется в строке номера.
     *
     * Счётчик — 3 знака (000-999), не 2 как у акта: у акта нагрузка
     * дополнительно делилась на "ремонт"/"обычный" по отдельным счётчикам,
     * у заявки такого деления нет и не будет — весь дневной объём идёт
     * через один общий счётчик буквы участка. Проверено по истории на
     * реальных данных 2026-09-12: префикс "i" разгонялся до 95 заявок в
     * день — 2 знака (потолок 99) не дали бы нужного запаса.
     *
     * $date — дата, от которой считается число в номере. По умолчанию
     * сегодня (обычное создание заявки). При смене участка на уже
     * СУЩЕСТВУЮЩЕЙ заявке (см. TicketController::update()) сюда нужно
     * передавать дату СОЗДАНИЯ этой заявки, а не сегодняшнюю — номер мог
     * уже быть показан/напечатан абоненту, и он не должен "переехать"
     * задним числом на день правки.
     */
    public static function generateNumber(?string $serviceTypeName = null, ?\Carbon\Carbon $date = null): string
    {
        // Определяем префикс по направлению
        if ($serviceTypeName) {
            $lower = mb_strtolower($serviceTypeName);
            if (str_contains($lower, 'интернет') || str_contains($lower, 'inet')) {
                $prefix = 'i';
            } elseif (str_contains($lower, 'ктв') || str_contains($lower, 'ctv') || str_contains($lower, 'кабел')) {
                $prefix = 'c';
            } else {
                $prefix = 'Т';
            }
        } else {
            $prefix = 'Т';
        }

        return static::nextNumberForPrefix($prefix, $date ?? now());
    }

    /**
     * lockForUpdate() защищает от гонки только внутри активной транзакции
     * (см. Ticket::createWithGeneratedNumber() / updateWithGeneratedNumber()
     * ниже, где это гарантировано) — без транзакции блокировка снимается
     * сразу после SELECT. Приём — тот же, что у Act::nextNumberForPrefix(),
     * уже проверен в бою на конкурентных закрытиях актов.
     */
    private static function nextNumberForPrefix(string $prefix, \Carbon\Carbon $date): string
    {
        $searchPrefix = $prefix . '-' . $date->format('ymd');
        $searchLen    = mb_strlen($searchPrefix);

        $lastNumber = static::withTrashed()
            ->where('number', 'LIKE', $searchPrefix . '%')
            ->orderByRaw('CAST(SUBSTRING(number, ' . ($searchLen + 1) . ') AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('number');

        $lastNum = $lastNumber ? (int) mb_substr($lastNumber, $searchLen) : 0;

        $candidate = $lastNum + 1;
        while (static::withTrashed()->where('number', $searchPrefix . str_pad($candidate, 3, '0', STR_PAD_LEFT))->exists()) {
            $candidate++;
        }

        return $searchPrefix . str_pad($candidate, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Создать заявку с автогенерированным номером устойчиво к гонке
     * параллельных созданий — аналог Act::createWithGeneratedNumber().
     * lockForUpdate() внутри $numberResolver защищает обычный случай (есть
     * что блокировать), retry здесь — редкий случай "первая заявка дня с
     * этой буквой", когда блокировать ещё нечего.
     */
    public static function createWithGeneratedNumber(array $data, \Closure $numberResolver, int $maxAttempts = 5): self
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return static::create($data + ['number' => $numberResolver()]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23000' || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Регенерация номера при смене участка на уже существующей заявке —
     * та же связка блокировка+retry, но для update(), а не create().
     * Оборачивает и генерацию, и сохранение в одну транзакцию: без этого
     * lockForUpdate() внутри $numberResolver защитил бы только SELECT, а
     * блокировка снялась бы ДО записи нового номера.
     */
    public static function updateWithGeneratedNumber(self $ticket, array $data, \Closure $numberResolver, int $maxAttempts = 5): self
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return \Illuminate\Support\Facades\DB::transaction(function () use ($ticket, $data, $numberResolver) {
                    $data['number'] = $numberResolver();
                    $ticket->update($data);
                    return $ticket;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() !== '23000' || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }
    }

    public function isClosed(): bool
    {
        return (bool) $this->closed_at;
    }

    /**
     * Сколько дней просрочена заявка: желаемое время выезда (scheduled_at) в
     * прошлом (по дате, не по времени суток) и статус ещё не финальный.
     * Требует загруженной связи 'status' (иначе лишний запрос на каждую заявку).
     */
    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->scheduled_at) return null;
        if ($this->status && $this->status->is_final) return null;

        $scheduledDate = $this->scheduled_at->copy()->startOfDay();
        $today = now()->startOfDay();
        if (!$scheduledDate->lt($today)) return null;

        return (int) $scheduledDate->diffInDays($today);
    }
}
