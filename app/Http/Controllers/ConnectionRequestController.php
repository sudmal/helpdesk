<?php

namespace App\Http\Controllers;

use App\Models\Act;
use App\Models\Brigade;
use App\Models\ConnectionRequest;
use App\Models\ConnectionRequestLog;
use App\Models\Material;
use App\Models\Promotion;
use App\Models\ServiceType;
use App\Models\Territory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConnectionRequestController extends Controller
{
    public function index(Request $request)
    {
        $user            = $request->user();
        $userTerritories = $this->getUserTerritories($user);

        // ?territory= из URL проверяется на принадлежность зоне видимости
        // пользователя — раньше принимался как есть, вкладки самого
        // интерфейса такое значение не предложили бы, но URL можно
        // подменить вручную (2026-08-04).
        $territory = $request->get('territory') ?: null;
        if ($territory && !$userTerritories->pluck('id')->contains((int) $territory)) {
            $territory = null;
        }

        // Сортировка "по важности": требующие прозвона (needs_callback) --
        // наверх независимо от статуса (это горящее действие), закрытые
        // (выполнено, либо отклонено/отказ и прозвон уже не нужен) -- вниз, как
        // полностью завершённые. Остальное (ожидает/назначено) -- посередине,
        // внутри каждой группы сначала новые.
        // Основной список тоже скоупится по территориям (2026-08-04) — раньше
        // фильтровались только счётчики/выпадающий список, сам список заявок
        // на подключение был виден целиком любому пользователю.
        $query = ConnectionRequest::with(['assignee', 'creator', 'materials', 'territory', 'brigade', 'serviceType', 'act'])
            ->when(!$user->isAdmin(), fn($q) => $q->whereIn('territory_id', $userTerritories->pluck('id')))
            ->when($territory, fn($q) => $q->where('territory_id', $territory))
            ->when($request->boolean('trashed'), fn($q) => $q->onlyTrashed())
            ->orderByRaw("
                CASE
                    WHEN needs_callback = 1 THEN 0
                    WHEN status IN ('closed', 'rejected', 'cancelled') THEN 2
                    ELSE 1
                END
            ")
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('service_type')) {
            $query->where('service_type_id', $request->service_type);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('phone', 'like', $s)
                  ->orWhere('address_string', 'like', $s);
            });
        }

        $pendingByTerritory = ConnectionRequest::where('status', 'pending')
            ->whereIn('territory_id', $userTerritories->pluck('id'))
            ->selectRaw('territory_id, COUNT(*) as cnt')
            ->groupBy('territory_id')
            ->pluck('cnt', 'territory_id');

        $overdueByTerritory = ConnectionRequest::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', today())
            ->whereIn('territory_id', $userTerritories->pluck('id'))
            ->selectRaw('territory_id, COUNT(*) as cnt')
            ->groupBy('territory_id')
            ->pluck('cnt', 'territory_id');

        return Inertia::render('ConnectionRequests/Index', [
            'requests'           => $query->paginate(50)->withQueryString(),
            'filters'            => $request->only(['status', 'search', 'territory', 'service_type', 'trashed']),
            'territories'        => $userTerritories->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'brigades'           => Brigade::with('territories:id,name')->orderBy('name')->get(['id', 'name']),
            'serviceTypes'       => ServiceType::active()->get(['id', 'name', 'color']),
            'selectedTerritory'  => $territory ? (int)$territory : null,
            'pendingByTerritory' => $pendingByTerritory,
            'totalPending'       => $pendingByTerritory->sum(),
            'overdueByTerritory' => $overdueByTerritory,
            'materialsCatalog'   => Material::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'unit', 'price']),
            'promotions'         => Promotion::active()->get(['id', 'name', 'price']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'phone'          => 'required|string|max:30',
            'address_string' => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'territory_id'   => 'required|exists:territories,id',
            // Акт по заявке жёстко привязан к бригаде (ресурсы, отчётность) —
            // см. память project-acts-feature, "Заявки на подключение".
            'brigade_id'     => 'required|exists:brigades,id',
            // Участок (тип услуги) — выбирается один раз здесь и используется
            // при закрытии для номера акта (in-/cn-) вместо повторного вопроса.
            'service_type_id' => 'required|exists:service_types,id',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        $req = ConnectionRequest::create($data);
        $this->logEvent($req, $request->user()->id, 'created');

        return back()->with('success', 'Заявка на подключение создана');
    }

    public function update(Request $request, ConnectionRequest $connectionRequest)
    {
        // Закрытые/отклонённые/отказные заявки — финальное состояние, редактированию
        // не подлежат (см. память проекта, project-connection-feasibility).
        // Забытые материалы к уже выполненной заявке добавляются отдельным
        // путём — addAct() ниже, а не через этот метод.
        if (in_array($connectionRequest->status, ['closed', 'rejected', 'cancelled'])) {
            return back()->withErrors([
                'status' => 'Закрытые, отклонённые и отказные заявки редактированию не подлежат.',
            ])->withInput();
        }

        $data = $request->validate([
            'name'           => 'sometimes|required|string|max:100',
            'phone'          => 'sometimes|required|string|max:30',
            'address_string' => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'status'         => 'sometimes|in:pending,scheduled,rejected,closed',
            'scheduled_at'   => 'nullable|date',
            'notes'          => 'nullable|string|max:2000',
            'territory_id'   => 'nullable|exists:territories,id',
            'brigade_id'     => 'nullable|exists:brigades,id',
            'service_type_id' => 'nullable|exists:service_types,id',
        ]);

        if (isset($data['status'])) {
            // needs_callback/"Прозвонил" -- целиком история про ответ монтажника
            // "Невозможно" (см. feasibility() ниже). Прямое "Отклонить" оператором
            // (минуя монтажника) этот флаг не трогает -- иначе телефон в меню
            // повиснет без кнопки, которой его снять (после rejected кнопки
            // "Прозвонил" в интерфейсе больше нет, см. Index.vue).
            $data['needs_callback'] = false;
        }

        // Дату можно назначать только после того, как монтажник подтвердил
        // техническую возможность подключения -- иначе оператор рискует
        // пообещать клиенту дату раньше, чем известно, возможно ли вообще
        // подключить (см. память проекта, project-connection-feasibility).
        // Прямое отклонение (Отклонить) оператором -- по-прежнему без гейта,
        // это отдельный, независимый путь.
        if (($data['status'] ?? null) === 'scheduled' && $connectionRequest->feasibility !== 'possible') {
            return back()->withErrors([
                'status' => 'Сначала нужен ответ монтажника: возможно ли подключение.',
            ])->withInput();
        }

        $oldStatus      = $connectionRequest->status;
        $oldScheduledAt = $connectionRequest->scheduled_at;
        $connectionRequest->update($data);

        $scheduledAtChanged = isset($data['scheduled_at'])
            && optional($oldScheduledAt)->format('Y-m-d H:i:s') !== \Carbon\Carbon::parse($data['scheduled_at'])->format('Y-m-d H:i:s');

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $notes = $data['notes'] ?? null;
            $meta  = isset($data['scheduled_at']) ? ['scheduled_at' => $data['scheduled_at']] : null;
            $this->logEvent($connectionRequest, $request->user()->id, $data['status'], $notes, $meta);
        } elseif ($scheduledAtChanged) {
            $this->logEvent($connectionRequest, $request->user()->id, 'scheduled',
                $data['notes'] ?? null, ['scheduled_at' => $data['scheduled_at']]);
        } else {
            $this->logEvent($connectionRequest, $request->user()->id, 'edited');
        }

        // Монтажнику бригады -- уведомление (Telegram/MAX/push), когда дату
        // назначили впервые или перенесли (не при каждом редактировании).
        if ($connectionRequest->status === 'scheduled'
            && (($data['status'] ?? null) === 'scheduled' || $scheduledAtChanged)) {
            dispatch(function () use ($connectionRequest) {
                \App\Notifications\ConnectionScheduledNotification::dispatch(
                    $connectionRequest->fresh(['brigade.members.role'])
                );
            })->afterResponse();
        }

        return back()->with('success', 'Заявка обновлена');
    }

    /**
     * Материалы теперь формируют полноценный Акт (Act + ActMaterial + ActHistory,
     * тот же workflow согласования Бригадир -> ПЭО/Логистика -> Абонотдел, что и
     * у заявок), а не пишутся на connection_request напрямую и не под свободный
     * текстовый "номер акта" — см. память project-acts-feature, "Заявки на
     * подключение". Act.type всегда 'regular' (репейр-акты сюда не относятся,
     * это всегда новое подключение) — участок (тип услуги) влияет только на
     * префикс номера акта (in-/cn-) и теперь берётся из самой заявки
     * (service_type_id, выбирается при создании), а не спрашивается заново здесь.
     */
    public function close(Request $request, ConnectionRequest $connectionRequest)
    {
        $request->validate([
            'notes'                    => 'nullable|string|max:2000',
            'materials'               => 'nullable|array',
            'materials.*.material_id' => 'required_with:materials.*|integer|exists:materials,id',
            'materials.*.quantity'    => 'required_with:materials.*|numeric|min:0.01',
            // Акция — фиксированная цена абонента, отдельно от реальной стоимости
            // материалов (та по-прежнему идёт в Логистику как есть) — см. память
            // project-acts-feature, "Акции по подключениям".
            'promotion_id'             => [
                'nullable', 'integer', 'exists:promotions,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && empty($request->input('materials'))) {
                        $fail('Акция применяется только вместе с материалами (нужен акт).');
                    }
                },
            ],
        ]);

        if (!empty($request->materials) && !$connectionRequest->service_type_id) {
            return back()->withErrors([
                'service_type_id' => 'У заявки не указан участок (тип услуги) — укажите его в редактировании заявки перед закрытием с материалами.',
            ])->withInput();
        }

        $actNumber = null;
        $promotion = $request->filled('promotion_id') ? Promotion::find($request->promotion_id) : null;

        // attempts=3: см. Act::createWithGeneratedNumber() — конкурентное закрытие
        // с тем же префиксом номера акта может словить deadlock на lockForUpdate(),
        // Laravel в этом случае полностью переиграет транзакцию.
        DB::transaction(function () use ($connectionRequest, $request, $promotion, &$actNumber) {
            $connectionRequest->update([
                'status'         => 'closed',
                'notes'          => $request->notes,
                'needs_callback' => false,
            ]);

            if (!empty($request->materials)) {
                $act = Act::createWithGeneratedNumber([
                    'connection_request_id' => $connectionRequest->id,
                    'type'                  => 'regular',
                    'status'                => 'pending_foreman',
                    'created_by'            => $request->user()->id,
                    // Снапшот на момент закрытия (как price_at_time у ActMaterial) —
                    // будущее изменение цены акции в справочнике не переписывает
                    // задним числом уже закрытые акты.
                    'promotion_id'    => $promotion?->id,
                    'promotion_name'  => $promotion?->name,
                    'promotion_price' => $promotion?->price,
                ], fn() => Act::generateNumberForConnectionRequest($connectionRequest));
                $actNumber = $act->number;

                foreach ($request->materials as $item) {
                    $material = Material::find($item['material_id']);
                    if (!$material) continue;
                    $act->materials()->create([
                        'material_id'   => $material->id,
                        'material_name' => $material->name,
                        'material_code' => $material->code,
                        'material_unit' => $material->unit,
                        'price_at_time' => $material->price,
                        'quantity'      => $item['quantity'],
                        'created_by'    => $request->user()->id,
                    ]);
                }

                $act->history()->create([
                    'user_id' => $request->user()->id,
                    'action'  => 'created',
                ]);
            }
        }, 3);

        $this->logEvent($connectionRequest, $request->user()->id, 'closed',
            $request->notes,
            $actNumber ? ['act_number' => $actNumber] : null);

        return back()->with('success', 'Подключение выполнено');
    }

    /**
     * Забытые материалы к уже выполненной заявке -- заявку закрыли без акта
     * (материалы не добавили: забыли, поспешили), а материалы и финансы всё
     * равно нужно учитывать. Только бригадир этой бригады (или admin как
     * подстраховка, тот же паттерн, что и ActPolicy::foremanReview) может
     * задним числом создать акт -- и только если акта у заявки ещё нет
     * вообще (если акт уже есть, но материалы забыли в нём самом, это
     * ActPolicy::editMaterials, отдельный, уже существующий путь, пока акт
     * не утверждён). Созданный акт идёт по обычному циклу согласования
     * (pending_foreman -> ...), включая самоутверждение тем же бригадиром --
     * см. память project-connection-feasibility, это осознанно разрешено.
     */
    public function addAct(Request $request, ConnectionRequest $connectionRequest)
    {
        abort_unless($connectionRequest->status === 'closed', 422, 'Акт можно добавить только к выполненной заявке.');
        abort_if($connectionRequest->act()->exists(), 422, 'У заявки уже есть акт.');
        abort_unless(
            $request->user()->isAdmin()
                || ($request->user()->isForeman() && $request->user()->brigades->pluck('id')->contains($connectionRequest->brigade_id)),
            403,
            'Добавить акт задним числом может только бригадир этой бригады.'
        );

        $request->validate([
            'materials'                => 'required|array|min:1',
            'materials.*.material_id'  => 'required|integer|exists:materials,id',
            'materials.*.quantity'     => 'required|numeric|min:0.01',
            'promotion_id'             => 'nullable|integer|exists:promotions,id',
        ]);

        if (!$connectionRequest->service_type_id) {
            return back()->withErrors([
                'service_type_id' => 'У заявки не указан участок (тип услуги) — сначала укажите его.',
            ])->withInput();
        }

        $promotion = $request->filled('promotion_id') ? Promotion::find($request->promotion_id) : null;
        $actNumber = null;

        DB::transaction(function () use ($connectionRequest, $request, $promotion, &$actNumber) {
            $act = Act::createWithGeneratedNumber([
                'connection_request_id' => $connectionRequest->id,
                'type'                  => 'regular',
                'status'                => 'pending_foreman',
                'created_by'            => $request->user()->id,
                'promotion_id'    => $promotion?->id,
                'promotion_name'  => $promotion?->name,
                'promotion_price' => $promotion?->price,
            ], fn() => Act::generateNumberForConnectionRequest($connectionRequest));
            $actNumber = $act->number;

            foreach ($request->materials as $item) {
                $material = Material::find($item['material_id']);
                if (!$material) continue;
                $act->materials()->create([
                    'material_id'   => $material->id,
                    'material_name' => $material->name,
                    'material_code' => $material->code,
                    'material_unit' => $material->unit,
                    'price_at_time' => $material->price,
                    'quantity'      => $item['quantity'],
                    'created_by'    => $request->user()->id,
                ]);
            }

            $act->history()->create([
                'user_id' => $request->user()->id,
                'action'  => 'created',
            ]);
        }, 3);

        $this->logEvent($connectionRequest, $request->user()->id, 'act_added_retroactively',
            'Акт добавлен задним числом (материалы забыли учесть при закрытии)',
            ['act_number' => $actNumber]);

        return back()->with('success', 'Акт создан');
    }

    public function markCalled(ConnectionRequest $connectionRequest)
    {
        $data = ['needs_callback' => false];

        // Прозвон после ответа монтажника "Невозможно" -- это звонок клиенту
        // с отказом, поэтому сразу переводим заявку в rejected (без отдельного
        // клика "Отклонить"). Если needs_callback был выставлен по другой
        // причине (например при назначении даты) -- просто сбрасываем флаг.
        if ($connectionRequest->feasibility === 'impossible' && $connectionRequest->status === 'pending') {
            $data['status'] = 'rejected';
        }

        $connectionRequest->update($data);
        $this->logEvent($connectionRequest, request()->user()->id, 'called_back');
        if (($data['status'] ?? null) === 'rejected') {
            $this->logEvent($connectionRequest, request()->user()->id, 'rejected', 'Автоматически отклонено после прозвона (монтажник: невозможно)');
        }
        return back()->with('success', 'Отмечено: прозвонили');
    }

    /**
     * Ответ монтажника на вопрос "возможно ли подключение" -- новый
     * обязательный шаг между созданием заявки и назначением даты
     * (см. память проекта, project-connection-feasibility).
     */
    public function feasibility(Request $request, ConnectionRequest $connectionRequest)
    {
        // Бригадир исходно не был сюда включён (см. память проекта,
        // project-connection-feasibility, план был "любой монтажник бригады") --
        // но бригадир так же полноценно отвечает за техвозможность своей
        // бригады и уже утверждает акты/добавляет их задним числом, так что
        // отсутствие доступа у него было пробелом, а не сознательным
        // ограничением. Обнаружено 2026-07-27: монтажник ошибочно ответил на
        // чужую заявку, вернул в pending, а бригадир той же бригады не смог
        // ответить повторно -- "нет доступа".
        abort_unless(
            $request->user()->isTechnician() || $request->user()->isForeman()
                || $request->user()->isAdmin() || $request->user()->isHeadSupport(),
            403,
            'Ответить может только монтажник или бригадир.'
        );

        $data = $request->validate([
            'answer'  => 'required|in:possible,impossible',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Оба ответа монтажника требуют звонка клиенту -- либо согласовать
        // дату (possible), либо сообщить об отказе (impossible) -- поэтому
        // needs_callback (телефон в меню) загорается в любом случае.
        $update = [
            'feasibility'         => $data['answer'],
            'feasibility_comment' => $data['comment'] ?? null,
            'feasibility_by'      => $request->user()->id,
            'feasibility_at'      => now(),
            'needs_callback'      => true,
        ];

        $connectionRequest->update($update);
        $this->logEvent(
            $connectionRequest, $request->user()->id,
            $data['answer'] === 'possible' ? 'feasibility_possible' : 'feasibility_impossible',
            $data['comment'] ?? null
        );

        return back()->with('success', 'Ответ сохранён');
    }

    /**
     * Отказ абонента -- он сам передумал уже после того, как оставил заявку
     * (в отличие от "rejected", который отражает решение оператора/монтажника,
     * например техническую невозможность). Отдельный статус и отдельный лог,
     * чтобы это различие не терялось при разборе истории/отчётах.
     */
    public function cancel(Request $request, ConnectionRequest $connectionRequest)
    {
        abort_if(
            in_array($connectionRequest->status, ['closed', 'rejected', 'cancelled']),
            422,
            'Заявка уже в финальном статусе.'
        );

        $data = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $connectionRequest->update([
            'status'         => 'cancelled',
            'notes'          => $data['comment'] ?? $connectionRequest->notes,
            'needs_callback' => false,
        ]);

        $this->logEvent($connectionRequest, $request->user()->id, 'cancelled', $data['comment'] ?? null);

        return back()->with('success', 'Заявка отмечена как «Отказ»');
    }

    public function destroy(Request $request, ConnectionRequest $connectionRequest)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:2000']);

        // Мягкое удаление (SoftDeletes) -- запись остаётся в БД и в логе,
        // просто пропадает из списка по умолчанию (см. index(), фильтр
        // trashed). Не путать со статусом rejected/cancelled -- это отдельный,
        // независимый механизм административной чистки (дубли, брак),
        // см. память проекта, project-connection-feasibility.
        $this->logEvent($connectionRequest, $request->user()->id, 'deleted', $data['reason'] ?? null);
        $connectionRequest->delete();

        return back()->with('success', 'Заявка удалена');
    }

    public function detail($id)
    {
        $connectionRequest = ConnectionRequest::withTrashed()->findOrFail($id);
        $connectionRequest->load(['creator', 'assignee', 'territory', 'brigade', 'serviceType', 'materials', 'logs.user', 'act.materials', 'act.promotion']);
        return response()->json($connectionRequest);
    }

    private function logEvent(ConnectionRequest $req, ?int $userId, string $action, ?string $notes = null, ?array $meta = null): void
    {
        ConnectionRequestLog::create([
            'connection_request_id' => $req->id,
            'user_id' => $userId,
            'action'  => $action,
            'notes'   => $notes,
            'meta'    => $meta,
        ]);
    }

    // Единая формула видимости по территориям (2026-08-04) — territoryScopeIds()
    // для всех, кроме admin. Раньше при пустом итоговом списке территорий
    // код молча откатывался на "показать вообще все территории" (та же
    // дыра, что уже находили и чинили в Заявках/Актах/Адресах/Дашборде
    // 2026-08-04) — теперь пустой список так и остаётся пустым.
    private function getUserTerritories($user)
    {
        if ($user->isAdmin()) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $ids = $user->territoryScopeIds();
        return Territory::whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->get();
    }
}
