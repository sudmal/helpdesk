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

        $territory = $request->get('territory') ?: null;

        $query = ConnectionRequest::with(['assignee', 'creator', 'materials', 'territory', 'brigade', 'serviceType', 'act'])
            ->when($territory, fn($q) => $q->where('territory_id', $territory))
            ->latest();

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
            'filters'            => $request->only(['status', 'search', 'territory']),
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
            $data['needs_callback'] = in_array($data['status'], ['scheduled', 'rejected']);
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

    public function markCalled(ConnectionRequest $connectionRequest)
    {
        $connectionRequest->update(['needs_callback' => false]);
        $this->logEvent($connectionRequest, request()->user()->id, 'called_back');
        return back()->with('success', 'Отмечено: прозвонили');
    }

    public function destroy(ConnectionRequest $connectionRequest)
    {
        $connectionRequest->delete();
        return back()->with('success', 'Заявка удалена');
    }

    public function detail(ConnectionRequest $connectionRequest)
    {
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

    private function getUserTerritories($user)
    {
        if ($user->hasPermission('*') || $user->hasPermission('settings.*')) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $ids = collect();
        $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
        if ($brigadeIds->isNotEmpty()) {
            $ids = $ids->merge(
                Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
            );
        }
        $ids = $ids->merge($user->territories()->pluck('territories.id'))->unique();
        if ($ids->isNotEmpty()) {
            return Territory::whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->get();
        }
        return Territory::orderBy('sort_order')->orderBy('name')->get();
    }
}
