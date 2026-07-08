<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ShiftDefinition extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // MySQL TIME всегда отдаёт "HH:MM:SS" независимо от того, что записали --
    // нормализуем до "HH:MM" на чтении, чтобы фронт (<input type="time">,
    // без секунд) и валидация (date_format:H:i) видели один и тот же формат
    // туда-обратно. Без этого несохранённое (нетронутое) поле времени в PUT
    // приходит как "21:00:00" и падает на валидации -- ровно так и было найдено.
    protected function startTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value ? substr($value, 0, 5) : $value);
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value ? substr($value, 0, 5) : $value);
    }

    /** Пересекает ли смена полночь (ночная смена вида 21:00-08:00) */
    public function crossesMidnight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /** Границы конкретного экземпляра смены, начавшегося в $date (Y-m-d) */
    public function boundsFor(string $date): array
    {
        $start = \Carbon\Carbon::parse($date . ' ' . $this->start_time);
        $end   = \Carbon\Carbon::parse($date . ' ' . $this->end_time);
        if ($this->crossesMidnight()) {
            $end->addDay();
        }
        return [$start, $end];
    }
}
