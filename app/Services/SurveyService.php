<?php

namespace App\Services;

use App\Models\{Act, SurveyQuestion, SurveyTarget, SystemSetting};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Опросные листы по актам (2026-09-24): какие акты подлежат опросу, через сколько
 * дней звонить абоненту, активные вопросы. Настройки — вкладка "Настройки опроса"
 * раздела Акты (survey_questions / survey_targets / system_settings survey.*).
 */
class SurveyService
{
    public const KINDS = [
        'connection' => 'Заявки на подключение (новое подключение)',
        'switch'     => 'Заявки на переключение на PON',
    ];

    public function delayDays(): int
    {
        return max(0, (int) SystemSetting::get('survey.delay_days', 3));
    }

    public function startDate(): string
    {
        return (string) SystemSetting::get('survey.start_date', now()->toDateString());
    }

    public function activeQuestions()
    {
        return SurveyQuestion::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Акты, подлежащие опросу: тип заявки акта (или вид заявки на подключение)
     * входит в survey_targets, акт создан не раньше survey.start_date. К запросу
     * присоединены заявка, заявка на подключение, адрес, бригада и сам опрос (s.*).
     */
    public function eligibleQuery(): Builder
    {
        $types = SurveyTarget::whereNotNull('ticket_type_id')->pluck('ticket_type_id')->all();
        $kinds = SurveyTarget::whereNotNull('connection_kind')->pluck('connection_kind')->all();

        return DB::table('acts as a')
            ->leftJoin('tickets as t', 't.id', '=', 'a.ticket_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.type_id')
            ->leftJoin('connection_requests as cr', 'cr.id', '=', 'a.connection_request_id')
            ->leftJoin('addresses as ad', 'ad.id', '=', 't.address_id')
            ->leftJoin('brigades as b', 'b.id', '=', DB::raw('COALESCE(t.brigade_id, cr.brigade_id)'))
            ->leftJoin('act_surveys as s', 's.act_id', '=', 'a.id')
            ->whereNull('t.deleted_at')
            ->whereNull('cr.deleted_at')
            ->where('a.created_at', '>=', $this->startDate() . ' 00:00:00')
            ->where(function ($q) use ($types, $kinds) {
                $q->where(fn($x) => $x->whereNotNull('a.ticket_id')->whereIn('t.type_id', $types))
                  ->orWhere(fn($x) => $x->whereNotNull('a.connection_request_id')->whereIn('cr.kind', $kinds));
            });
    }

    public function isEligible(Act $act): bool
    {
        return $this->eligibleQuery()->where('a.id', $act->id)->exists();
    }

    /** Поля для списка/карточки: кому звонить, по какому адресу, кто монтировал. */
    public function contactSelect(): string
    {
        $delay = $this->delayDays();

        return "a.id as act_id, a.number as act_number, a.created_at as act_created_at,
            DATE_ADD(a.created_at, INTERVAL {$delay} DAY) as due_at,
            tt.name as type_name, cr.kind as request_kind,
            COALESCE(NULLIF(ad.subscriber_name, ''), cr.name) as subscriber_name,
            COALESCE(NULLIF(t.phone, ''), NULLIF(ad.phone, ''), cr.phone) as phone,
            COALESCE(NULLIF(CONCAT_WS(', ', ad.city, ad.street, ad.building), ''), cr.address_string) as address_text,
            COALESCE(NULLIF(t.apartment, ''), NULLIF(ad.apartment, '')) as apartment,
            b.id as brigade_id, b.name as brigade_name,
            s.id as survey_id, s.status as survey_status, s.attempts, s.last_attempt_at, s.last_attempt_note,
            s.completed_at, s.overall_comment,
            (SELECT AVG(x.rating) FROM act_survey_answers x WHERE x.survey_id = s.id) as avg_rating";
    }

    /** Фильтр по статусу очереди: due | upcoming | completed | declined. */
    public function applyStatus(Builder $q, string $status): Builder
    {
        $delay = $this->delayDays();

        return match ($status) {
            'completed' => $q->where('s.status', 'completed'),
            'declined'  => $q->where('s.status', 'declined'),
            'upcoming'  => $q->where(fn($x) => $x->whereNull('s.id')->orWhere('s.status', 'pending'))
                              ->whereRaw("DATE_ADD(a.created_at, INTERVAL {$delay} DAY) > NOW()"),
            default     => $q->where(fn($x) => $x->whereNull('s.id')->orWhere('s.status', 'pending'))
                              ->whereRaw("DATE_ADD(a.created_at, INTERVAL {$delay} DAY) <= NOW()"),
        };
    }
}
