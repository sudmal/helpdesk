<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Act extends Model
{
    /**
     * Гейт Абонотдела на уровне модели (не только Policy/контроллера) — акт с
     * реальным типом (не legacy-бэкфилл, там type=null) нельзя перевести в
     * completed, пока не проведены все требуемые для этого типа стороны:
     * regular — ПЭО + Логистика, repair — только Логистика. См. память
     * project-acts-feature. Бэкфилл старых ticket_materials сознательно
     * оставляет type=null и обходит эту проверку — это исторические данные
     * до появления самого workflow.
     */
    protected static function booted(): void
    {
        static::saving(function (Act $act) {
            if (!$act->isDirty('status') || $act->status !== 'completed' || $act->type === null) {
                return;
            }

            $logisticsDone = $act->logistics_processed_at !== null;
            $peoDone       = $act->type !== 'regular' || $act->peo_processed_at !== null;

            if (!$logisticsDone || !$peoDone) {
                throw new \RuntimeException(
                    "Акт {$act->number} нельзя завершить: не проведены все требуемые отделы (ПЭО/Логистика)."
                );
            }
        });
    }

    protected $fillable = [
        'ticket_id', 'number', 'type', 'status', 'created_by',
        'foreman_reviewed_by', 'foreman_reviewed_at', 'foreman_return_comment',
        'peo_processed_by', 'peo_processed_at',
        'logistics_processed_by', 'logistics_processed_at',
        'subscriber_dept_completed_by', 'subscriber_dept_completed_at',
        'materials_changed_at',
    ];

    protected $casts = [
        'foreman_reviewed_at'          => 'datetime',
        'peo_processed_at'             => 'datetime',
        'logistics_processed_at'       => 'datetime',
        'subscriber_dept_completed_at' => 'datetime',
        'materials_changed_at'         => 'datetime',
    ];

    public function ticket(): BelongsTo   { return $this->belongsTo(Ticket::class); }
    public function materials(): HasMany  { return $this->hasMany(ActMaterial::class); }
    public function history(): HasMany    { return $this->hasMany(ActHistory::class)->latest(); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function foremanReviewer(): BelongsTo         { return $this->belongsTo(User::class, 'foreman_reviewed_by'); }
    public function peoProcessor(): BelongsTo            { return $this->belongsTo(User::class, 'peo_processed_by'); }
    public function logisticsProcessor(): BelongsTo      { return $this->belongsTo(User::class, 'logistics_processed_by'); }
    public function subscriberDeptCompleter(): BelongsTo { return $this->belongsTo(User::class, 'subscriber_dept_completed_by'); }

    /**
     * Номер акта: <буква участка заявки><буква типа акта>-NNNNNN.
     * Буква участка — та же логика, что в Ticket::generateNumber() (i=интернет, c=КТВ, Т=прочее).
     * Буква типа: r = обычный, v = ремонт/восстановление.
     * Нумерация сквозная в рамках префикса, никогда не сбрасывается.
     */
    public static function generateNumber(Ticket $ticket, string $type): string
    {
        $serviceTypeName = $ticket->serviceType?->name;
        $lower = mb_strtolower((string) $serviceTypeName);
        if (str_contains($lower, 'интернет') || str_contains($lower, 'inet')) {
            $baseLetter = 'i';
        } elseif (str_contains($lower, 'ктв') || str_contains($lower, 'ctv') || str_contains($lower, 'кабел')) {
            $baseLetter = 'c';
        } else {
            $baseLetter = 'Т';
        }

        $typeLetter = $type === 'repair' ? 'v' : 'r';
        $prefix = $baseLetter . $typeLetter;
        $prefixLen = mb_strlen($prefix);

        $lastNumber = static::where('number', 'LIKE', $prefix . '-%')
            ->orderByRaw('CAST(SUBSTRING(number, ' . ($prefixLen + 2) . ') AS UNSIGNED) DESC')
            ->value('number');

        $lastNum = $lastNumber ? (int) mb_substr($lastNumber, $prefixLen + 1) : 0;

        $candidate = $lastNum + 1;
        while (static::where('number', $prefix . '-' . str_pad($candidate, 6, '0', STR_PAD_LEFT))->exists()) {
            $candidate++;
        }

        return $prefix . '-' . str_pad($candidate, 6, '0', STR_PAD_LEFT);
    }
}
