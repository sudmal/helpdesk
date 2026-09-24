<?php

namespace App\Http\Controllers;

use App\Models\{Act, ActSurvey, ActSurveyAnswer, SurveyQuestion, SurveyTarget, SystemSetting, TicketType};
use App\Services\SurveyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Опросные листы по актам (2026-09-24). Оператор через несколько дней после
 * подключения звонит абоненту, задаёт вопросы и ставит оценки 1-5 с необязательным
 * комментарием; по результатам строятся отчёты по бригадам (вкладка "Оценки
 * абонентов" в Отчётах). Права: surveys.conduct — проводить опросы, surveys.manage —
 * настраивать вопросы и правила (admin покрыт "*").
 */
class SurveyController extends Controller
{
    public function __construct(private SurveyService $surveys) {}

    private function authorizeConduct(Request $request): void
    {
        abort_unless($request->user()->hasPermission('surveys.conduct'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('surveys.manage'), 403);
    }

    // ── Очередь опросов ─────────────────────────────────────────────────

    public function queue(Request $request)
    {
        $this->authorizeConduct($request);
        $user   = $request->user();
        $status = in_array($request->status, ['due', 'upcoming', 'completed', 'declined']) ? $request->status : 'due';

        $base = $this->surveys->eligibleQuery();
        if (!$user->isAdmin()) {
            $base->whereIn(DB::raw('COALESCE(ad.territory_id, cr.territory_id)'), $user->territoryScopeIds());
        }
        if ($request->filled('brigade')) {
            $request->brigade === 'none'
                ? $base->whereNull(DB::raw('COALESCE(t.brigade_id, cr.brigade_id)'))
                : $base->where(DB::raw('COALESCE(t.brigade_id, cr.brigade_id)'), $request->brigade);
        }

        $counts = [];
        foreach (['due', 'upcoming', 'completed', 'declined'] as $st) {
            $counts[$st] = $this->surveys->applyStatus(clone $base, $st)->count();
        }

        $q = $this->surveys->applyStatus(clone $base, $status)->selectRaw($this->surveys->contactSelect());
        $status === 'completed' || $status === 'declined'
            ? $q->orderByDesc('s.updated_at')
            : $q->orderBy('a.created_at');

        return response()->json([
            'status' => $status,
            'counts' => $counts,
            'rows'   => $q->paginate(25),
        ]);
    }

    // ── Опрос по конкретному акту (форма / блок в карточке акта) ─────────

    public function showAct(Request $request, Act $act)
    {
        $this->authorize('view', $act);

        $row = $this->surveys->eligibleQuery()->where('a.id', $act->id)
            ->selectRaw($this->surveys->contactSelect())->first();

        $survey = ActSurvey::with(['answers', 'completer:id,name'])->where('act_id', $act->id)->first();

        // Акт мог перестать подпадать под настройки, но опрос по нему уже есть — покажем его
        if (!$row && !$survey) {
            return response()->json(['eligible' => false]);
        }

        return response()->json([
            'eligible'     => (bool) $row,
            'can_conduct'  => $request->user()->hasPermission('surveys.conduct'),
            'info'         => $row,
            'questions'    => $this->surveys->activeQuestions()->map(fn($q) => ['id' => $q->id, 'text' => $q->text])->values(),
            'survey'       => $survey ? [
                'status'            => $survey->status,
                'attempts'          => $survey->attempts,
                'last_attempt_at'   => $survey->last_attempt_at,
                'last_attempt_note' => $survey->last_attempt_note,
                'overall_comment'   => $survey->overall_comment,
                'completed_at'      => $survey->completed_at,
                'completed_by'      => $survey->completer?->name,
                'answers'           => $survey->answers->map(fn($a) => [
                    'question_id' => $a->question_id, 'question_text' => $a->question_text,
                    'rating' => $a->rating, 'comment' => $a->comment,
                ])->values(),
            ] : null,
        ]);
    }

    public function complete(Request $request, Act $act)
    {
        $this->authorizeConduct($request);
        $this->authorize('view', $act);

        $data = $request->validate([
            'answers'                => 'required|array|min:1',
            'answers.*.question_id'  => 'required|integer|exists:survey_questions,id',
            'answers.*.rating'       => 'nullable|integer|min:1|max:5',
            'answers.*.comment'      => 'nullable|string|max:2000',
            'overall_comment'        => 'nullable|string|max:5000',
        ]);

        if (!collect($data['answers'])->contains(fn($a) => !empty($a['rating']))) {
            throw ValidationException::withMessages(['answers' => 'Поставьте оценку хотя бы по одному вопросу.']);
        }

        $questions = SurveyQuestion::whereIn('id', collect($data['answers'])->pluck('question_id'))->get()->keyBy('id');

        DB::transaction(function () use ($act, $data, $request, $questions) {
            $survey = ActSurvey::firstOrCreate(['act_id' => $act->id]);
            // Заменяем только ответы на вопросы, присланные формой. Ответы на вопросы,
            // которые с тех пор отключили/удалили, остаются нетронутыми — история не теряется.
            $survey->answers()->whereIn('question_id', collect($data['answers'])->pluck('question_id'))->delete();
            foreach ($data['answers'] as $a) {
                ActSurveyAnswer::create([
                    'survey_id'     => $survey->id,
                    'question_id'   => $a['question_id'],
                    'question_text' => $questions[$a['question_id']]->text,
                    'rating'        => $a['rating'] ?? null,
                    'comment'       => filled($a['comment'] ?? null) ? trim($a['comment']) : null,
                ]);
            }
            $survey->update([
                'status'          => 'completed',
                'overall_comment' => filled($data['overall_comment'] ?? null) ? trim($data['overall_comment']) : null,
                'completed_by'    => $request->user()->id,
                'completed_at'    => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /** "Не дозвонился" — попытка засчитана, акт остаётся в очереди. */
    public function attempt(Request $request, Act $act)
    {
        $this->authorizeConduct($request);
        $this->authorize('view', $act);
        $data = $request->validate(['note' => 'nullable|string|max:500']);

        $survey = ActSurvey::firstOrCreate(['act_id' => $act->id]);
        $survey->update([
            'attempts'          => $survey->attempts + 1,
            'last_attempt_at'   => now(),
            'last_attempt_note' => $data['note'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /** Абонент отказался от опроса / опрос не нужен — акт уходит из очереди. */
    public function decline(Request $request, Act $act)
    {
        $this->authorizeConduct($request);
        $this->authorize('view', $act);
        $data = $request->validate(['note' => 'nullable|string|max:500']);

        $survey = ActSurvey::firstOrCreate(['act_id' => $act->id]);
        $survey->update([
            'status'            => 'declined',
            'last_attempt_at'   => now(),
            'last_attempt_note' => $data['note'] ?? null,
            'completed_by'      => $request->user()->id,
        ]);

        return response()->json(['ok' => true]);
    }

    // ── Настройки опроса ────────────────────────────────────────────────

    public function settings(Request $request)
    {
        $this->authorizeManage($request);

        $selectedTypes = SurveyTarget::whereNotNull('ticket_type_id')->pluck('ticket_type_id')->all();
        $selectedKinds = SurveyTarget::whereNotNull('connection_kind')->pluck('connection_kind')->all();

        return response()->json([
            'questions' => SurveyQuestion::withCount('answers')->orderBy('sort_order')->orderBy('id')->get(),
            'ticket_types' => TicketType::orderBy('id')->get(['id', 'name'])
                ->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'selected' => in_array($t->id, $selectedTypes)])->values(),
            'kinds' => collect(SurveyService::KINDS)
                ->map(fn($label, $key) => ['key' => $key, 'label' => $label, 'selected' => in_array($key, $selectedKinds)])->values(),
            'delay_days' => $this->surveys->delayDays(),
            'start_date' => $this->surveys->startDate(),
        ]);
    }

    public function storeQuestion(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['text' => 'required|string|max:500']);

        $q = SurveyQuestion::create([
            'text' => trim($data['text']), 'is_active' => true,
            'sort_order' => (int) SurveyQuestion::max('sort_order') + 1,
        ]);

        return response()->json($q);
    }

    public function updateQuestion(Request $request, SurveyQuestion $question)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['text' => 'sometimes|required|string|max:500', 'is_active' => 'sometimes|boolean']);
        if (isset($data['text'])) $data['text'] = trim($data['text']);
        $question->update($data);

        return response()->json($question);
    }

    public function destroyQuestion(Request $request, SurveyQuestion $question)
    {
        $this->authorizeManage($request);

        // Есть ответы — не удаляем, а выключаем, чтобы отчёты не потеряли историю
        if ($question->answers()->exists()) {
            $question->update(['is_active' => false]);
            return response()->json(['deactivated' => true]);
        }
        $question->delete();

        return response()->json(['deleted' => true]);
    }

    public function reorderQuestions(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:survey_questions,id']);

        foreach ($data['ids'] as $i => $id) {
            SurveyQuestion::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function updateTargets(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'ticket_type_ids'    => 'array',
            'ticket_type_ids.*'  => 'integer|exists:ticket_types,id',
            'connection_kinds'   => 'array',
            'connection_kinds.*' => 'in:' . implode(',', array_keys(SurveyService::KINDS)),
        ]);

        DB::transaction(function () use ($data) {
            SurveyTarget::query()->delete();
            foreach (array_unique($data['ticket_type_ids'] ?? []) as $id) SurveyTarget::create(['ticket_type_id' => $id]);
            foreach (array_unique($data['connection_kinds'] ?? []) as $k) SurveyTarget::create(['connection_kind' => $k]);
        });

        return response()->json(['ok' => true]);
    }

    public function updateGeneral(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['delay_days' => 'required|integer|min:0|max:90', 'start_date' => 'required|date']);

        SystemSetting::set('survey.delay_days', $data['delay_days']);
        SystemSetting::set('survey.start_date', Carbon::parse($data['start_date'])->toDateString());

        return response()->json(['ok' => true]);
    }

    // ── Отчёт "Оценки абонентов" (вкладка в общих Отчётах) ───────────────

    /**
     * Средние оценки по бригадам и вопросам за период (по дате проведения опроса).
     * Бригада — та, что выполняла заявку акта. Оценка "не ответил" (rating NULL)
     * в средние не входит.
     */
    public function report(Request $request)
    {
        $from = Carbon::parse($request->get('from', now()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->get('to',   now()->toDateString()))->endOfDay();

        $base = fn() => DB::table('act_survey_answers as an')
            ->join('act_surveys as s', 's.id', '=', 'an.survey_id')
            ->join('acts as a', 'a.id', '=', 's.act_id')
            ->leftJoin('tickets as t', 't.id', '=', 'a.ticket_id')
            ->leftJoin('connection_requests as cr', 'cr.id', '=', 'a.connection_request_id')
            ->leftJoin('brigades as b', 'b.id', '=', DB::raw('COALESCE(t.brigade_id, cr.brigade_id)'))
            ->where('s.status', 'completed')
            ->whereBetween('s.completed_at', [$from, $to]);

        $qKey = "COALESCE(CAST(an.question_id AS CHAR), an.question_text)";

        // Подпись колонки — актуальный текст вопроса (если вопрос ещё существует), иначе снимок из ответа
        $cells = $base()->leftJoin('survey_questions as sq', 'sq.id', '=', 'an.question_id')->whereNotNull('an.rating')
            ->selectRaw("COALESCE(b.id, 0) as bkey, COALESCE(b.name, 'Без бригады') as brigade_name, {$qKey} as qkey, COALESCE(MAX(sq.text), MAX(an.question_text)) as qtext, AVG(an.rating) as avg, COUNT(*) as n")
            ->groupBy('bkey', 'brigade_name', 'qkey')->get();

        $surveyCounts = $base()->selectRaw("COALESCE(b.id, 0) as bkey, COUNT(DISTINCT s.id) as surveys")
            ->groupBy('bkey')->pluck('surveys', 'bkey');

        $brigades  = [];
        $questions = [];
        $tot = ['sum' => 0, 'n' => 0];
        foreach ($cells as $c) {
            $questions[$c->qkey] ??= ['key' => $c->qkey, 'text' => $c->qtext, 'sum' => 0, 'n' => 0];
            $questions[$c->qkey]['sum'] += $c->avg * $c->n;
            $questions[$c->qkey]['n']   += $c->n;

            $brigades[$c->bkey] ??= ['key' => (int) $c->bkey, 'name' => $c->brigade_name, 'surveys' => (int) ($surveyCounts[$c->bkey] ?? 0), 'sum' => 0, 'n' => 0, 'by_question' => []];
            $brigades[$c->bkey]['sum'] += $c->avg * $c->n;
            $brigades[$c->bkey]['n']   += $c->n;
            $brigades[$c->bkey]['by_question'][$c->qkey] = ['avg' => round($c->avg, 2), 'n' => (int) $c->n];

            $tot['sum'] += $c->avg * $c->n;
            $tot['n']   += $c->n;
        }

        $brigadeRows = collect($brigades)->map(fn($b) => [
            'key' => $b['key'], 'name' => $b['name'], 'surveys' => $b['surveys'],
            'avg' => $b['n'] ? round($b['sum'] / $b['n'], 2) : null, 'by_question' => $b['by_question'],
        ])->sortByDesc('avg')->values();

        $questionRows = collect($questions)->map(fn($q) => [
            'key' => $q['key'], 'text' => $q['text'], 'avg' => $q['n'] ? round($q['sum'] / $q['n'], 2) : null,
        ])->values();

        // Комментарии: общий комментарий опроса + комментарии к отдельным вопросам
        $comments = DB::table('act_surveys as s')
            ->join('acts as a', 'a.id', '=', 's.act_id')
            ->leftJoin('tickets as t', 't.id', '=', 'a.ticket_id')
            ->leftJoin('connection_requests as cr', 'cr.id', '=', 'a.connection_request_id')
            ->leftJoin('brigades as b', 'b.id', '=', DB::raw('COALESCE(t.brigade_id, cr.brigade_id)'))
            ->where('s.status', 'completed')
            ->whereBetween('s.completed_at', [$from, $to])
            ->whereRaw("(s.overall_comment IS NOT NULL OR EXISTS(SELECT 1 FROM act_survey_answers x WHERE x.survey_id = s.id AND x.comment IS NOT NULL))")
            ->orderByDesc('s.completed_at')->limit(100)
            ->selectRaw("s.id, a.id as act_id, a.number as act_number, COALESCE(b.name, 'Без бригады') as brigade_name, s.completed_at, s.overall_comment,
                (SELECT AVG(x.rating) FROM act_survey_answers x WHERE x.survey_id = s.id) as avg_rating")
            ->get();

        $byQuestion = DB::table('act_survey_answers')->whereIn('survey_id', $comments->pluck('id'))->whereNotNull('comment')
            ->get(['survey_id', 'question_text', 'rating', 'comment'])->groupBy('survey_id');

        return response()->json([
            'brigades'  => $brigadeRows,
            'questions' => $questionRows,
            'total'     => ['avg' => $tot['n'] ? round($tot['sum'] / $tot['n'], 2) : null, 'surveys' => $brigadeRows->sum('surveys')],
            'comments'  => $comments->map(fn($c) => [
                'act_id' => $c->act_id, 'act_number' => $c->act_number, 'brigade' => $c->brigade_name,
                'completed_at' => $c->completed_at, 'avg' => $c->avg_rating !== null ? round($c->avg_rating, 1) : null,
                'overall_comment' => $c->overall_comment,
                'answers' => ($byQuestion[$c->id] ?? collect())->map(fn($a) => ['q' => $a->question_text, 'rating' => $a->rating, 'comment' => $a->comment])->values(),
            ])->values(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
