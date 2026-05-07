<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Builder;

class Ticket extends Model
{
    use SoftDeletes;
protected $fillable = [
        'number', 'address_id', 'apartment', 'type_id', 'service_type_id', 'status_id', 'brigade_id',
        'created_by', 'assigned_to', 'description', 'phone', 'contract_no',
        'priority', 'scheduled_at', 'started_at', 'paused_at', 'closed_at',
        'close_notes', 'act_number',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'paused_at'    => 'datetime',
        'closed_at'    => 'datetime',
    ];

    // === Relations ===
    public function address(): BelongsTo      { return $this->belongsTo(Address::class); }
    public function serviceType(): BelongsTo  { return $this->belongsTo(\App\Models\ServiceType::class, 'service_type_id'); }
    public function type(): BelongsTo      { return $this->belongsTo(TicketType::class); }
    public function status(): BelongsTo    { return $this->belongsTo(TicketStatus::class); }
    public function brigade(): BelongsTo   { return $this->belongsTo(Brigade::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments(): HasMany    { return $this->hasMany(TicketComment::class)->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class); }
        public function materials(): HasMany
    {
        return $this->hasMany(TicketMaterial::class);
    }

    public function history(): HasMany     { return $this->hasMany(TicketHistory::class)->latest(); }

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

            // Поиск по адресу с разбором улица+дом
            if ($buildingHint) {
                $q->orWhereHas('address', function ($a) use ($streetTerm, $buildingHint) {
                    if ($streetTerm) $a->search($streetTerm);
                    $a->where('building', $buildingHint);
                });
                // Квартира из tickets
                if ($aptHint) {
                    $q->orWhere(function ($sub) use ($streetTerm, $buildingHint, $aptHint) {
                        $sub->where('apartment', $aptHint)
                            ->whereHas('address', function ($a) use ($streetTerm, $buildingHint) {
                                if ($streetTerm) $a->search($streetTerm);
                                $a->where('building', $buildingHint);
                            });
                    });
                }
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
    public static function generateNumber(?string $serviceTypeName = null): string
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

        // Берём максимальный номер с этим префиксом и увеличиваем
        $lastNumber = static::withTrashed()
            ->where('number', 'LIKE', $prefix . '-%')
            ->orderByRaw('CAST(SUBSTRING(number, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
            ->value('number');

        if ($lastNumber) {
            $lastNum = (int) substr($lastNumber, strlen($prefix) + 1);
        } else {
            $lastNum = 0;
        }

        // Ищем первый свободный номер начиная с lastNum+1
        $candidate = $lastNum + 1;
        while (static::withTrashed()->where('number', $prefix . '-' . str_pad($candidate, 6, '0', STR_PAD_LEFT))->exists()) {
            $candidate++;
        }

        return $prefix . '-' . str_pad($candidate, 6, '0', STR_PAD_LEFT);
    }

    public function isClosed(): bool
    {
        return (bool) $this->closed_at;
    }
}
