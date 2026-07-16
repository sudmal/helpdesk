<?php

namespace App\Http\Controllers;

use App\Models\{Act, ActMaterial, Brigade, Material, Territory};
use App\Services\ActService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActController extends Controller
{
    public function __construct(private ActService $actService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Act::class);
        $user = auth()->user();
        $tab  = in_array($request->tab, ['active', 'archive', 'reports']) ? $request->tab : 'active';

        // Отчёты — сама вкладка видна всем, кто видит Акты, но содержимое (пока
        // это перенесённый сюда "Расход материалов" из общих Отчётов) доступно
        // только тем, у кого reports.view/admin/head_support — ПЭО/Логистика/
        // Абонотдел получили reports.view ещё в миграции ролей 2026-07-15,
        // бригадир/монтажник — нет (см. память project-acts-feature).
        if ($tab === 'reports') {
            return Inertia::render('Acts/Index', [
                'tab'             => $tab,
                'acts'            => null,
                'filters'         => [],
                'authUserId'      => $user->id,
                'canViewReports'  => $user->isAdmin() || $user->isHeadSupport() || $user->hasPermission('reports.view'),
            ]);
        }

        // Бригадир/монтажник — строго по бригаде заявки (ticket.brigade_id), а не по
        // пересечению территорий бригад (сузили 2026-07-15, см. память project-acts-feature).
        $brigadeScopeIds = collect();
        $userTerritories = collect();
        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeScopeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            if ($brigadeScopeIds->isEmpty()) {
                $userTerritories = $user->territories()->pluck('territories.id');
            }
        } elseif ($user->isPeo() || $user->isLogistics() || $user->isSubscriberDept()) {
            $userTerritories = $user->territories()->pluck('territories.id');
        }
        // admin/head_support/operator — без ограничений (обе коллекции остаются пустыми).

        // Джойны нужны для сортировки/группировки по территории и бригаде —
        // задел под будущие отчёты по актам (см. память project-acts-feature).
        // Акт теперь бывает от заявки (tickets) ИЛИ от заявки на подключение
        // (connection_requests) — территория/бригада берутся COALESCE'ом
        // источника, который реально заполнен для этого акта.
        $query = Act::query()
            ->select('acts.*')
            ->leftJoin('tickets', 'tickets.id', '=', 'acts.ticket_id')
            ->leftJoin('addresses', 'addresses.id', '=', 'tickets.address_id')
            ->leftJoin('connection_requests', 'connection_requests.id', '=', 'acts.connection_request_id')
            ->leftJoin('territories', function ($join) {
                $join->on('territories.id', '=', DB::raw('COALESCE(addresses.territory_id, connection_requests.territory_id)'));
            })
            ->leftJoin('brigades', function ($join) {
                $join->on('brigades.id', '=', DB::raw('COALESCE(tickets.brigade_id, connection_requests.brigade_id)'));
            })
            ->with([
                'ticket:id,number,address_id,brigade_id,type_id,service_type_id',
                'ticket.address:id,city,street,building,apartment,territory_id',
                'ticket.address.territory:id,name',
                'ticket.brigade:id,name',
                'connectionRequest:id,name,phone,address_string,territory_id,brigade_id',
                'connectionRequest.territory:id,name',
                'connectionRequest.brigade:id,name',
                'materials',
                'creator:id,name',
                'foremanReviewer:id,name',
                'peoProcessor:id,name',
                'logisticsProcessor:id,name',
                'subscriberDeptCompleter:id,name',
            ])
            ->when($brigadeScopeIds->isNotEmpty(), fn($q) =>
                $q->whereIn(DB::raw('COALESCE(tickets.brigade_id, connection_requests.brigade_id)'), $brigadeScopeIds)
            )
            ->when($userTerritories->isNotEmpty(), fn($q) =>
                $q->whereIn(DB::raw('COALESCE(addresses.territory_id, connection_requests.territory_id)'), $userTerritories)
            )
            ->when($request->type, fn($q) => $q->where('acts.type', $request->type));

        if ($tab === 'archive') {
            // Полностью завершённые акты уходят сюда с главной вкладки и здесь
            // ищутся/сортируются как обычный архив, а не очередь на согласование.
            $query->where('acts.status', 'completed');

            // Легаси-акты (type=null, бэкфилл ticket_materials до появления workflow —
            // см. память project-acts-feature) по умолчанию скрыты из архива как
            // "неправильные" в глазах пользователя, но НЕ удаляются — можно вернуть
            // ?legacy=show. Тип — надёжный признак, дата ненадёжна (новые акты со
            // старыми датами тоже возможны при повторном бэкфилле).
            if ($request->legacy !== 'show') {
                $query->whereNotNull('acts.type');
            }

            $query->when($request->search, function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('acts.number', 'like', $s)
                       ->orWhereHas('ticket', fn($t) => $t->where('number', 'like', $s))
                       ->orWhereHas('ticket.address', fn($a) => $a
                           ->where('street', 'like', $s)
                           ->orWhere('city', 'like', $s)
                           ->orWhere('building', 'like', $s))
                       ->orWhereHas('connectionRequest', fn($c) => $c
                           ->where('address_string', 'like', $s)
                           ->orWhere('name', 'like', $s)
                           ->orWhere('phone', 'like', $s));
                });
            });

            $sortable = [
                'completed_at' => 'acts.subscriber_dept_completed_at',
                'created_at'   => 'acts.created_at',
                'number'       => 'acts.number',
            ];
            $sortColumn = $sortable[$request->sort] ?? $sortable['completed_at'];
            $sortDir    = $request->sort_dir === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortColumn, $sortDir);
        } else {
            $query->where('acts.status', '!=', 'completed')
                ->when($request->status, fn($q) => $q->where('acts.status', $request->status))
                ->orderBy('territories.sort_order')
                ->orderBy('territories.name')
                ->orderBy('brigades.name')
                ->orderByDesc('acts.created_at');
        }

        $acts = $query->paginate(30)->withQueryString();

        return Inertia::render('Acts/Index', [
            'tab'        => $tab,
            'acts'       => $acts,
            'filters'    => $request->only(['status', 'type', 'search', 'sort', 'sort_dir', 'legacy']),
            'authUserId' => $user->id,
        ]);
    }

    public function show(Act $act): Response
    {
        $this->authorize('view', $act);

        $act->load([
            'ticket.address.territory', 'ticket.type', 'ticket.serviceType',
            'connectionRequest.territory', 'connectionRequest.brigade',
            'materials.material',
            'history.user',
            'creator', 'foremanReviewer', 'peoProcessor', 'logisticsProcessor', 'subscriberDeptCompleter',
        ]);

        $user = auth()->user();

        return Inertia::render('Acts/Show', [
            'act' => $act,
            'can' => [
                'foremanReview'    => $user->can('foremanReview', $act),
                'processPeo'       => $user->can('processPeo', $act),
                'processLogistics' => $user->can('processLogistics', $act),
                'complete'         => $user->can('complete', $act),
                'editMaterials'    => $user->can('editMaterials', $act),
                'acknowledge'      => $user->can('acknowledge', $act),
            ],
            'materialsCatalog' => Material::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'unit', 'price']),
        ]);
    }

    /**
     * Печатная форма акта — доступна только после утверждения бригадиром
     * (не в pending_foreman), см. память project-acts-feature. Список
     * материалов — только реально использованные (act.materials), не общий
     * каталог: бумажный шаблон, на котором это основано, содержит фиксированный
     * список из 16 позиций с пустыми клетками под ручное заполнение — тут
     * вместо клеток печатаются только позиции, которые реально есть на акте.
     */
    public function print(Act $act): \Illuminate\Http\Response
    {
        $this->authorize('view', $act);
        abort_if($act->status === 'pending_foreman', 404, 'Печать доступна после утверждения бригадиром.');

        $act->load(['ticket.address', 'connectionRequest', 'materials', 'creator']);

        $customerName = $act->ticket
            ? $act->ticket->address?->subscriber_name
            : $act->connectionRequest?->name;

        $address = $act->ticket
            ? $act->ticket->address?->full_address
            : $act->connectionRequest?->address_string;

        $total = $act->materials->sum(fn($m) => $m->price_at_time * $m->quantity);

        // Только дата, без ФИО обработавшего — печатная форма это физический
        // документ под ручную подпись/штамп отдела, печатать туда имя из
        // системы вместо места под подпись странно выглядит (пользователь
        // 2026-07-16). Кто именно обработал — и так видно в самой карточке
        // акта в системе, дублировать на бумаге не нужно.
        $mark = fn(?string $at) => $at ? \Carbon\Carbon::parse($at)->format('d.m.Y') : '______________';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('acts.print', [
            'act'                => $act,
            'customerName'       => $customerName,
            'address'            => $address,
            'createdAt'          => $act->created_at->format('d.m.Y'),
            'installerName'      => $act->creator?->name,
            'total'              => $total,
            'markForeman'        => $mark($act->foreman_reviewed_at),
            'markPeo'            => $act->type === 'regular' ? $mark($act->peo_processed_at) : 'не требуется',
            'markLogistics'      => $mark($act->logistics_processed_at),
            'markSubscriberDept' => $mark($act->subscriber_dept_completed_at),
        ]);

        return $pdf->stream("act-{$act->number}.pdf");
    }

    public function approve(Act $act): RedirectResponse
    {
        $this->authorize('foremanReview', $act);
        $this->actService->approve($act, auth()->user());

        return back()->with('success', 'Акт утверждён');
    }

    public function processPeo(Act $act): RedirectResponse
    {
        $this->authorize('processPeo', $act);
        $user = auth()->user();

        $act->peo_processed_by = $user->id;
        $act->peo_processed_at = now();
        $this->recomputeAfterProcessing($act);
        $act->save();

        $this->logHistory($act, $user->id, 'peo_processed');

        return back()->with('success', 'Отмечено как обработано ПЭО');
    }

    public function processLogistics(Act $act): RedirectResponse
    {
        $this->authorize('processLogistics', $act);
        $user = auth()->user();

        $act->logistics_processed_by = $user->id;
        $act->logistics_processed_at = now();
        $this->recomputeAfterProcessing($act);
        $act->save();

        $this->logHistory($act, $user->id, 'logistics_processed');

        return back()->with('success', 'Отмечено как обработано Логистикой');
    }

    public function complete(Act $act): RedirectResponse
    {
        $this->authorize('complete', $act);
        $user = auth()->user();

        $act->update([
            'status'                       => 'completed',
            'subscriber_dept_completed_by' => $user->id,
            'subscriber_dept_completed_at' => now(),
        ]);
        $this->logHistory($act, $user->id, 'completed');

        return back()->with('success', 'Акт завершён и отправлен в архив');
    }

    /** Бригадир добавляет новую позицию материала — только в pending_foreman (см. ActPolicy::editMaterials) */
    public function addMaterial(Request $request, Act $act): RedirectResponse
    {
        $this->authorize('editMaterials', $act);
        $request->validate([
            'material_id' => 'required|exists:materials,id',
            'quantity'    => 'required|numeric|min:0.001',
        ]);

        $this->actService->addMaterial($act, auth()->user(), (int) $request->material_id, (float) $request->quantity);

        return back()->with('success', 'Материал добавлен в акт');
    }

    /** Бригадир меняет количество существующей позиции */
    public function updateMaterial(Request $request, Act $act, ActMaterial $material): RedirectResponse
    {
        $this->authorize('editMaterials', $act);
        abort_unless($material->act_id === $act->id, 404);
        $request->validate(['quantity' => 'required|numeric|min:0.001']);

        $this->actService->updateMaterial($act, $material, auth()->user(), (float) $request->quantity);

        return back()->with('success', 'Материал изменён');
    }

    /** Бригадир удаляет позицию — сама запись стирается, но остаётся в истории (для подсветки монтажнику) */
    public function removeMaterial(Act $act, ActMaterial $material): RedirectResponse
    {
        $this->authorize('editMaterials', $act);
        abort_unless($material->act_id === $act->id, 404);

        $this->actService->removeMaterial($act, $material, auth()->user());

        return back()->with('success', 'Материал удалён из акта');
    }

    /** Монтажник подтверждает, что увидел правки бригадира в составе акта */
    public function acknowledge(Act $act): RedirectResponse
    {
        $this->authorize('acknowledge', $act);
        $this->actService->acknowledge($act, auth()->user());

        return back()->with('success', 'Изменения подтверждены');
    }

    /**
     * Требуемые для гейта Абонотдела стороны зависят от типа акта:
     * regular — ПЭО + Логистика, repair — только Логистика (см. память project-acts-feature).
     * Статус становится pending_subscriber_dept, как только обработаны ВСЕ требуемые
     * стороны — для repair это происходит сразу после Логистики, минуя processing.
     */
    private function recomputeAfterProcessing(Act $act): void
    {
        $required = $act->type === 'regular' ? ['peo', 'logistics'] : ['logistics'];

        $done = collect($required)->every(function (string $side) use ($act) {
            $field = $side === 'peo' ? 'peo_processed_at' : 'logistics_processed_at';
            return $act->$field !== null;
        });

        $act->status = $done ? 'pending_subscriber_dept' : 'processing';
    }

    private function logHistory(
        Act $act, int $userId, string $action,
        ?string $field = null, ?string $old = null, ?string $new = null,
        ?int $relatedMaterialId = null
    ): void {
        $act->history()->create([
            'user_id'             => $userId,
            'action'              => $action,
            'field'               => $field,
            'old_value'           => $old,
            'new_value'           => $new,
            'related_material_id' => $relatedMaterialId,
        ]);
    }
}
