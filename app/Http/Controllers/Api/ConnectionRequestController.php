<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\ConnectionRequest;
use App\Models\ConnectionRequestLog;
use App\Models\Material;
use App\Models\Promotion;
use App\Models\Territory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectionRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user         = $request->user();
        $territories  = $this->getUserTerritories($user);
        $territoryIds = $territories->pluck('id');

        $query = ConnectionRequest::with(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'act'])
            ->whereIn('territory_id', $territoryIds)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'scheduled', 'rejected'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'closed')
                         ->where('updated_at', '>=', now()->subDays(2));
                  });
            })
            ->latest();

        if ($request->filled('territory_id')) {
            $query->where('territory_id', $request->territory_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('phone', 'like', $s)
                  ->orWhere('address_string', 'like', $s);
            });
        }

        $perPage = min((int)($request->per_page ?? 50), 100);
        $page    = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data'         => $page->map(fn($r) => $this->formatOne($r))->values(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'territories'  => $territories->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'synced_at'    => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'materials', 'act', 'logs.user']);
        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
            'address_string'  => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'territory_id'    => 'required|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        $cr = ConnectionRequest::create($data);
        $this->logEvent($cr, $request->user()->id, 'created');
        $cr->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'logs.user']);

        return response()->json($this->formatOne($cr), 201);
    }

    public function update(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'phone'           => 'sometimes|string|max:30',
            'address_string'  => 'sometimes|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'territory_id'    => 'sometimes|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'status'          => 'sometimes|in:pending,scheduled,rejected',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string|max:2000',
        ]);

        if (isset($data['status'])) {
            // needs_callback/"Прозвонил" -- целиком история про ответ монтажника
            // "Невозможно" (см. feasibility() ниже). Прямое отклонение оператором
            // (минуя монтажника) этот флаг не трогает.
            $data['needs_callback'] = false;
        }

        // Тот же гейт, что и в веб-версии (ConnectionRequestController::update):
        // назначать дату можно только после того, как монтажник ответил
        // "возможно" -- см. память проекта, project-connection-feasibility.
        if (($data['status'] ?? null) === 'scheduled' && $connectionRequest->feasibility !== 'possible') {
            return response()->json([
                'message' => 'Сначала нужен ответ монтажника: возможно ли подключение.',
                'errors'  => ['status' => ['Сначала нужен ответ монтажника: возможно ли подключение.']],
            ], 422);
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
        // назначили впервые или перенесли.
        if ($connectionRequest->status === 'scheduled'
            && (($data['status'] ?? null) === 'scheduled' || $scheduledAtChanged)) {
            dispatch(function () use ($connectionRequest) {
                \App\Notifications\ConnectionScheduledNotification::dispatch(
                    $connectionRequest->fresh(['brigade.members.role'])
                );
            })->afterResponse();
        }

        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'materials', 'logs.user']);

        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    /**
     * Закрытие с материалами создаёт полноценный Акт (Act/ActMaterial/ActHistory),
     * как и веб-версия (см. ConnectionRequestController::close, память
     * project-acts-feature) — раньше здесь писался только плоский текстовый
     * act_number, из-за чего заявки на подключение, закрытые с телефона,
     * не попадали в workflow согласования Бригадир→ПЭО/Логистика→Абонотдел.
     */
    public function close(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $request->validate([
            'notes'                    => 'nullable|string|max:2000',
            'materials'                => 'nullable|array',
            'materials.*.material_id'  => 'required_with:materials.*|integer|exists:materials,id',
            'materials.*.quantity'     => 'required_with:materials.*|numeric|min:0.01',
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
            return response()->json([
                'message' => 'У заявки не указан участок (тип услуги) — укажите его перед закрытием с материалами.',
                'errors'  => ['service_type_id' => ['У заявки не указан участок (тип услуги).']],
            ], 422);
        }

        $promotion = $request->filled('promotion_id') ? Promotion::find($request->promotion_id) : null;

        // attempts=3: см. Act::createWithGeneratedNumber() — конкурентное закрытие
        // с тем же префиксом номера акта может словить deadlock на lockForUpdate(),
        // Laravel в этом случае полностью переиграет транзакцию.
        $actNumber = null;

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

        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'act', 'logs.user']);

        return response()->json($this->formatOne($connectionRequest));
    }

    public function markCalled(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $data = ['needs_callback' => false];

        // Тот же принцип, что и в веб-версии: прозвон после "Невозможно" от
        // монтажника -- это звонок с отказом, сразу закрываем rejected.
        $autoRejected = $connectionRequest->feasibility === 'impossible' && $connectionRequest->status === 'pending';
        if ($autoRejected) {
            $data['status'] = 'rejected';
        }

        $connectionRequest->update($data);
        $this->logEvent($connectionRequest, $request->user()->id, 'called_back');
        if ($autoRejected) {
            $this->logEvent($connectionRequest, $request->user()->id, 'rejected', 'Автоматически отклонено после прозвона (монтажник: невозможно)');
        }

        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'logs.user']);
        return response()->json($this->formatOne($connectionRequest));
    }

    /**
     * Ответ монтажника на вопрос "возможно ли подключение" -- см. память
     * проекта, project-connection-feasibility, и веб-версию этого же экшна.
     */
    public function feasibility(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        // Бригадир так же полноценно отвечает за техвозможность своей бригады
        // (уже утверждает акты/добавляет их задним числом) -- отсутствие
        // доступа было пробелом, не сознательным ограничением, см. память
        // проекта, project-connection-feasibility (найдено 2026-07-27).
        $user = $request->user();
        if (!$user->isTechnician() && !$user->isForeman() && !$user->isAdmin() && !$user->isHeadSupport()) {
            return response()->json(['message' => 'Ответить может только монтажник или бригадир.'], 403);
        }

        $data = $request->validate([
            'answer'  => 'required|in:possible,impossible',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Оба ответа монтажника требуют звонка клиенту -- согласовать дату
        // (possible) либо сообщить об отказе (impossible).
        $update = [
            'feasibility'         => $data['answer'],
            'feasibility_comment' => $data['comment'] ?? null,
            'feasibility_by'      => $user->id,
            'feasibility_at'      => now(),
            'needs_callback'      => true,
        ];

        $connectionRequest->update($update);
        $this->logEvent(
            $connectionRequest, $user->id,
            $data['answer'] === 'possible' ? 'feasibility_possible' : 'feasibility_impossible',
            $data['comment'] ?? null
        );
        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'feasibilityByUser', 'logs.user']);

        return response()->json($this->formatOne($connectionRequest));
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

    public function destroy(ConnectionRequest $connectionRequest): JsonResponse
    {
        $connectionRequest->delete();
        return response()->json(['message' => 'Заявка удалена']);
    }

    private function formatOne(ConnectionRequest $r, bool $withMaterials = false): array
    {
        $data = [
            'id'             => $r->id,
            'name'           => $r->name,
            'phone'          => $r->phone,
            'address_string' => $r->address_string,
            'description'    => $r->description,
            'status'         => $r->status,
            'scheduled_at'   => $r->scheduled_at?->toIso8601String(),
            'notes'          => $r->notes,
            // act_number оставлен для обратной совместимости со старыми сборками
            // приложения — заполняется из старой плоской колонки, если она есть
            // (легаси-заявки), иначе из номера полноценного Акта (см. `act` ниже).
            'act_number'     => $r->act_number ?: $r->act?->number,
            'act'            => $r->relationLoaded('act') && $r->act ? [
                'id'                   => $r->act->id,
                'number'               => $r->act->number,
                'status'               => $r->act->status,
                'materials_changed_at' => $r->act->materials_changed_at?->toIso8601String(),
                'promotion_name'       => $r->act->promotion_name,
                'promotion_price'      => $r->act->promotion_price !== null ? (float) $r->act->promotion_price : null,
            ] : null,
            'needs_callback' => (bool) $r->needs_callback,
            'feasibility'         => $r->feasibility,
            'feasibility_comment' => $r->feasibility_comment,
            'feasibility_by'      => $r->feasibility_by,
            'feasibility_by_user' => $r->relationLoaded('feasibilityByUser') && $r->feasibilityByUser
                ? ['id' => $r->feasibilityByUser->id, 'name' => $r->feasibilityByUser->name] : null,
            'feasibility_at'      => $r->feasibility_at?->toIso8601String(),
            'territory'      => $r->territory ? ['id' => $r->territory->id, 'name' => $r->territory->name] : null,
            'service_type'   => $r->serviceType ? [
                'id'    => $r->serviceType->id,
                'name'  => $r->serviceType->name,
                'color' => $r->serviceType->color,
            ] : null,
            'creator'        => $r->creator?->name,
            'assigned_to'    => $r->assigned_to,
            'assignee'       => $r->assignee ? ['id' => $r->assignee->id, 'name' => $r->assignee->name] : null,
            'created_at'     => $r->created_at->toIso8601String(),
            'updated_at'     => $r->updated_at->toIso8601String(),
        ];

        if ($withMaterials) {
            $data['materials'] = ($r->relationLoaded('materials') ? $r->materials : collect())
                ->map(fn($m) => [
                    'id'            => $m->id,
                    'material_id'   => $m->material_id,
                    'name'          => $m->material_name,
                    'code'          => $m->material_code,
                    'unit'          => $m->material_unit,
                    'price_at_time' => (float) $m->price_at_time,
                    'quantity'      => (float) $m->quantity,
                    'total'         => round((float)$m->price_at_time * (float)$m->quantity, 2),
                ])->values()->all();

        }

        // История изменений заявки -- по аналогии с history у Актов (GET /acts/{id}).
        // Присутствует во всех ответах с одиночным объектом (show/store/update/
        // close/markCalled/feasibility, см. ->load(['logs.user']) в каждом методе),
        // но не в списке (GET /connection-requests) -- там 'logs' не eager-loaded,
        // relationLoaded() возвращает false, лишнего запроса на каждую строку нет.
        if ($r->relationLoaded('logs')) {
            $data['logs'] = $r->logs->sortByDesc('created_at')->values()->map(fn($l) => [
                'id'         => $l->id,
                'user'       => $l->user?->name,
                'action'     => $l->action,
                'notes'      => $l->notes,
                'meta'       => $l->meta,
                'created_at' => $l->created_at->toIso8601String(),
            ])->all();
        }

        return $data;
    }

    // Единая формула видимости по территориям (2026-08-04) — та же, что и
    // в веб-версии ConnectionRequestController::getUserTerritories(). Раньше
    // пустой итоговый список территорий откатывался на "показать все" (та
    // же дыра, что и везде) — теперь пустой список так и остаётся пустым.
    private function getUserTerritories($user)
    {
        if ($user->isAdmin()) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $ids = $user->territoryScopeIds();
        return Territory::whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->get();
    }
}
