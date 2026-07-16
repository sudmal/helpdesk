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
        'ticket_id', 'connection_request_id', 'number', 'type', 'status', 'created_by',
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

    public function ticket(): BelongsTo             { return $this->belongsTo(Ticket::class); }
    public function connectionRequest(): BelongsTo  { return $this->belongsTo(ConnectionRequest::class); }
    public function materials(): HasMany  { return $this->hasMany(ActMaterial::class); }
    public function history(): HasMany    { return $this->hasMany(ActHistory::class)->latest(); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function foremanReviewer(): BelongsTo         { return $this->belongsTo(User::class, 'foreman_reviewed_by'); }
    public function peoProcessor(): BelongsTo            { return $this->belongsTo(User::class, 'peo_processed_by'); }
    public function logisticsProcessor(): BelongsTo      { return $this->belongsTo(User::class, 'logistics_processed_by'); }
    public function subscriberDeptCompleter(): BelongsTo { return $this->belongsTo(User::class, 'subscriber_dept_completed_by'); }

    /**
     * Номер акта: <буква участка заявки><буква типа акта>-YYMMDDNN.
     * Буква участка — та же логика, что в Ticket::generateNumber() (i=интернет, c=КТВ, Т=прочее).
     * Буква типа: r = обычный, v = ремонт/восстановление.
     * Цифровая часть — см. nextNumberForPrefix().
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

        return static::nextNumberForPrefix($baseLetter . $typeLetter);
    }

    /**
     * Номер акта для заявки на подключение: <in|cn>-YYMMDDNN — буквенный префикс
     * определяется участком (ServiceType), который теперь выбирается один раз при
     * создании заявки на подключение (connection_requests.service_type_id), а не
     * заново при закрытии. Тип самого акта (Act.type) при этом всегда 'regular' —
     * см. память project-acts-feature, "Заявки на подключение" — регулярный/
     * ремонтный акт как ось не относится к новым подключениям, только к участку
     * в номере.
     */
    public static function generateNumberForConnectionRequest(ConnectionRequest $connectionRequest): string
    {
        $lower = mb_strtolower((string) $connectionRequest->serviceType?->name);
        $prefix = (str_contains($lower, 'ктв') || str_contains($lower, 'ctv') || str_contains($lower, 'кабел'))
            ? 'cn' : 'in';

        return static::nextNumberForPrefix($prefix);
    }

    /**
     * Цифровая часть номера (2026-07-16): YYMMDDNN — дата создания акта +
     * порядковый номер СЕГОДНЯ в рамках именно этого буквенного префикса
     * (свой отдельный счётчик с 01 на каждый префикс каждый день; префиксы
     * друг другу не мешают). Буквенная часть перед дефисом не менялась.
     *
     * Существующие акты со старой сквозной схемой (<префикс>-NNNNNN, без даты)
     * сознательно не переименовываются и не мешают новой генерации — LIKE-поиск
     * ищет строго по сегодняшней дате в качестве части префикса, старые шести-
     * значные номера под него не подпадают.
     */
    /**
     * lockForUpdate() — намеренно: без него это обычный consistent-read SELECT,
     * который в MySQL REPEATABLE READ (дефолт) не видит чужие коммиты, случившиеся
     * после старта нашей транзакции — включая коммит конкурента, случившийся между
     * НАШИМ чтением и его записью. Два монтажника, закрывающих заявки с одним
     * префиксом (например "ir") почти одновременно, иначе оба посчитают один и тот
     * же следующий номер. FOR UPDATE — locking read, всегда читает актуальные
     * закоммиченные данные и ставит блокировку: второй запрос физически подождёт
     * коммита первого и увидит уже его номер. См. createWithGeneratedNumber() —
     * связка "блокировка + retry на дубликат" целиком закрывает и обычный случай
     * (есть что блокировать), и редкий (первый акт дня с этим префиксом — блокировать
     * ещё нечего, тогда сработает retry на ошибке уникальности number).
     */
    private static function nextNumberForPrefix(string $prefix): string
    {
        $searchPrefix = $prefix . '-' . now()->format('ymd');
        $searchLen    = mb_strlen($searchPrefix);

        $lastNumber = static::where('number', 'LIKE', $searchPrefix . '%')
            ->orderByRaw('CAST(SUBSTRING(number, ' . ($searchLen + 1) . ') AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('number');

        $lastNum = $lastNumber ? (int) mb_substr($lastNumber, $searchLen) : 0;

        $candidate = $lastNum + 1;
        while (static::where('number', $searchPrefix . str_pad($candidate, 2, '0', STR_PAD_LEFT))->exists()) {
            $candidate++;
        }

        return $searchPrefix . str_pad($candidate, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Создать акт с автогенерированным номером устойчиво к гонке параллельных
     * закрытий (см. nextNumberForPrefix() — там блокировка на обычный случай,
     * здесь — retry-подстраховка на редкий случай "первый акт дня с этим
     * префиксом", когда блокировать ещё нечего). $numberResolver вызывается
     * заново на каждой попытке — при повторном вызове видит уже закоммиченный
     * номер конкурента (за счёт lockForUpdate) и посчитает следующий свободный.
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
                // Коллизия по уникальному number — конкурент забронировал этот номер
                // первым между нашим чтением и записью. Пробуем следующий кандидат.
            }
        }
    }
}
