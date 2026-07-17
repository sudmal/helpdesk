<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketType, TicketStatus, Brigade, Address, User, ServiceType, SystemSetting};
use App\Services\TicketService;
use App\Http\Requests\{StoreTicketRequest, UpdateTicketRequest, AddCommentRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ticket::class);
        $sort    = in_array($request->sort, ['number','created_at','scheduled_at','status_id','priority']) ? $request->sort : 'created_at';
        $sortDir = in_array($request->sortDir, ['asc','desc']) ? $request->sortDir : 'desc';

        $user = auth()->user();

        // Операторы, диспетчеры, руководство видят все заявки.
        // Бригадиры и монтажники — только по территориям своей бригады.
        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = \App\Models\Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            if ($brigadeIds->isNotEmpty()) {
                $userTerritories = \App\Models\Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id');
            } else {
                $userTerritories = $user->territories()->pluck('territories.id');
            }
        } else {
            $userTerritories = collect(); // нет фильтра — видят всё
        }

        $nonFinalStatusIds = Cache::remember('ts_non_final_ids', 3600,
            fn() => \App\Models\TicketStatus::where('is_final', false)->pluck('id'));
        $closedStatusId = Cache::remember('ts_closed_id', 3600,
            fn() => \App\Models\TicketStatus::where('slug', 'closed')->value('id'));

        $tickets = Ticket::with(['address', 'type', 'serviceType', 'status', 'brigade', 'creator', 'assignee'])
            ->withCount('comments')
            ->when($userTerritories->isNotEmpty(), fn($q) =>
                $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $userTerritories))
            )
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->type,   fn($q) => $q->where('type_id', $request->type))
            ->when($request->brigade,      fn($q) => $q->where('brigade_id', $request->brigade))
            ->when($request->service_type, fn($q) => $q->where('service_type_id', $request->service_type))
            ->when($request->overdue, fn($q) => $q->whereIn('status_id', $nonFinalStatusIds)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<', today()))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->when($request->date_from, fn($q) => $q->where('scheduled_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('scheduled_at', '<=', $request->date_to . ' 23:59:59'))
->when($request->input('closed_today'), function ($q) use ($request) {
                $q->where('status_id', $closedStatusId)->whereDate('closed_at', today());
                if ($request->input('closed_today') === 'auto') {
                    $q->where('close_notes', 'LIKE', '%просрочено%');
                } elseif ($request->input('closed_today') === 'manual') {
                    $q->where(function ($sub) {
                        $sub->whereNull('close_notes')
                            ->orWhere('close_notes', 'NOT LIKE', '%просрочено%');
                    });
                }
            })
            ->when($request->input('address_id'), function ($q) use ($request) {
                $q->where('address_id', $request->input('address_id'));
            })
            ->when($request->input('city') || $request->input('street') || $request->input('building'), function ($q) use ($request) {
                $q->whereHas('address', function ($a) use ($request) {
                    if ($request->input('city'))     $a->where('city',     $request->input('city'));
                    if ($request->input('street'))   $a->where('street',   $request->input('street'));
                    if ($request->input('building')) $a->where('building', $request->input('building'));
                });
            })
            ->when($request->input('apartment'), function ($q) use ($request) {
                $apt = $request->input('apartment');
                $q->where(function ($sub) use ($apt) {
                    $sub->where('apartment', $apt)
                        ->orWhereHas('address', fn($a) => $a->where('apartment', $apt));
                });
            })

            ->orderBy($sort, $sortDir)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets'  => $tickets,
            'filters'  => $request->only(['search', 'status', 'type', 'brigade', 'priority', 'date_from', 'date_to', 'address_id', 'city', 'street', 'building', 'apartment', 'service_type', 'overdue', 'closed_today', 'sort', 'sortDir']),
            'addressFilterLabel' => $request->address_id ? \App\Models\Address::find($request->address_id)?->full_address : null,
            'statuses' => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'types'    => TicketType::active()->get(['id', 'name', 'color']),
            'brigades'     => Brigade::orderBy('name')->get(['id', 'name']),
            'serviceTypes' => \App\Models\ServiceType::active()->get(['id', 'name', 'color']),
            'overdueCount' => Cache::remember('overdue_count', 60, fn() =>
                Ticket::whereIn('status_id', $nonFinalStatusIds)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<', today())
                    ->count()),
            'canCreate' => $user->can('create', Ticket::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Ticket::class);

        // Если передан address_id — подгружаем адрес и историю заявок по нему
        $address = null;
        $addressHistory = [];
        if ($request->address_id) {
            $address = Address::with('territory')->find($request->address_id);
            $addressHistory = Ticket::with(['type', 'status', 'creator'])
                ->where('address_id', $request->address_id)
                ->when($request->apartment, fn($q) => $q->where('apartment', $request->apartment))
                ->latest()
                ->take(50)
                ->get();
        }

        return Inertia::render('Tickets/Create', [
            'types'        => TicketType::active()->get(['id', 'name', 'color']),
            'serviceTypes' => ServiceType::active()->get(['id', 'name', 'color']),
            'statuses'     => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'brigades'     => Brigade::with('territories')->orderBy('name')->get(),
            'address'      => $address,
            'addressHistory' => $addressHistory,
            'initialPhone'    => $request->input('phone', ''),
            'initialApartment' => $request->input('apartment', ''),
'territories'  => \App\Models\Territory::orderBy('name')->get(['id', 'name']),
            'lanbillingEnabled' => (bool) \App\Models\SystemSetting::get('lanbilling_enabled', true),
            'settings'     => [
                'work_hours_start'      => SystemSetting::get('work_hours_start', '09:00'),
                'work_hours_end'        => SystemSetting::get('work_hours_end', '17:00'),
                'schedule_step_minutes' => SystemSetting::get('schedule_step_minutes', 30),
            ],
        ]);
    }

    public function store(StoreTicketRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        // Защита от случайного дубля (двойной клик по "Создать") -- тот же
        // адрес+телефон, открытая (не финальная) заявка, созданная только
        // что. Каждая заявка при этом остаётся полностью независимой сущностью
        // (свои комментарии/акты/история/переносы строго по своему ID) --
        // здесь только предотвращаем появление ВТОРОЙ такой заявки, никакого
        // связывания/слияния данных между ними нет и не будет.
        if (!empty($validated['address_id'])) {
            $duplicate = Ticket::where('address_id', $validated['address_id'])
                ->when(!empty($validated['phone']), fn($q) => $q->where('phone', $validated['phone']))
                ->whereHas('status', fn($s) => $s->where('is_final', false))
                ->where('created_at', '>=', now()->subMinutes(3))
                ->latest()
                ->first();
            if ($duplicate) {
                return back()->withErrors([
                    'address_id' => "Похоже на дубль: заявка {$duplicate->number} по этому адресу и телефону уже создана только что.",
                ])->withInput();
            }
        }

        // Проверка занятости временного слота
        $conflict = $this->ticketService->checkSlotConflict($validated);
        if ($conflict) {
            return back()->withErrors(['scheduled_at' => $conflict])->withInput();
        }

        $ticket = $this->ticketService->create($validated, auth()->user());

        // Сохраняем вложения если есть
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, auth()->user(), 'attachment');
            }
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Заявка создана');
    }

    public function show(Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'address.territory',
            'type', 'serviceType', 'status', 'brigade.members', 'creator', 'assignee',
            'comments.author', 'comments.attachments',
            'attachments.uploader',
            'act.materials', 'act.history.user',
            'history.user',
            'closedBy',
        ]);
        $ticket->append('days_overdue');

        // История заявок по этому адресу (кроме текущей)
        $addressHistory = $ticket->address_id

            ? Ticket::with(['type', 'status', 'act'])
                ->where('address_id', $ticket->address_id)
                ->where('id', '!=', $ticket->id)
                ->when($ticket->apartment, fn($q) =>
                    $q->where(function ($sub) use ($ticket) {
                        $sub->where('apartment', $ticket->apartment)
                            ->orWhereNull('apartment')
                            ->orWhere('apartment', '');
                    })
                )
                ->latest()
                ->take(20)
                ->get(['id', 'number', 'type_id', 'status_id', 'address_id',
                       'apartment', 'description', 'close_notes', 'created_at'])
            : [];

        return Inertia::render('Tickets/Show', [
            'ticket'         => $ticket,
            'addressHistory' => $addressHistory,
            'materialsCatalog' => \App\Models\Material::active()->orderBy('sort_order')->orderBy('name')->get(['id','code','name','unit','price']),
            'statuses'       => TicketStatus::active()->get(['id', 'name', 'color', 'slug', 'is_final']),
            'brigades'       => Brigade::with('members')->orderBy('name')->get(),
            'canEdit'        => auth()->user()->can('update', $ticket),
            'canAssign'      => auth()->user()->can('assign', $ticket),
            'canClose'       => auth()->user()->can('close', $ticket),
            'canComment'     => auth()->user()->can('comment', $ticket),
            'canDelete'      => auth()->user()->can('delete', $ticket),
            // Раньше "В работу"/"Пауза"/"Перенести" во фронте проверялись через
            // canClose — но у TicketPolicy это отдельные способности с другими
            // правилами (start/pause: только бригадир/монтажник/админ, без учёта
            // tickets.close; postpone: шире close — ещё head_support/operator).
            // Несовпадение приводило к тому, что кнопка показывалась не тем, кому
            // положено, и вела на 403 — см. память project-acts-feature, "403 на
            // недоступных действиях".
            'canStart'       => auth()->user()->can('start', $ticket),
            'canPause'       => auth()->user()->can('pause', $ticket),
            'canPostpone'    => auth()->user()->can('postpone', $ticket),
            'settings'       => [
                'lanbillingEnabled'    => (bool) \App\Models\SystemSetting::get('lanbilling_enabled', true),
                'work_hours_start'      => SystemSetting::get('work_hours_start', '09:00'),
                'work_hours_end'        => SystemSetting::get('work_hours_end', '17:00'),
                'schedule_step_minutes' => SystemSetting::get('schedule_step_minutes', 30),
            ],
        ]);
    }

    public function edit(Ticket $ticket): Response
    {
        $this->authorize('update', $ticket);

        $ticket->load(['address.territory', 'type', 'status', 'brigade', 'assignee']);

        return Inertia::render('Tickets/Edit', [
            'ticket'       => $ticket,
            'types'        => TicketType::active()->get(['id', 'name', 'color']),
            'statuses'     => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'brigades'     => Brigade::with('members')->orderBy('name')->get(),
            'serviceTypes' => ServiceType::active()->get(['id', 'name', 'color']),
            'settings' => [
                'lanbillingEnabled'    => (bool) \App\Models\SystemSetting::get('lanbilling_enabled', true),
                'work_hours_start'      => SystemSetting::get('work_hours_start', '09:00'),
                'work_hours_end'        => SystemSetting::get('work_hours_end', '17:00'),
                'schedule_step_minutes' => SystemSetting::get('schedule_step_minutes', 30),
            ],
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();

        // Обновляем префикс номера если изменился участок
        $newServiceTypeId = $data['service_type_id'] ?? null;
        if ($newServiceTypeId != $ticket->service_type_id) {
            $serviceTypeName = $newServiceTypeId
                ? ServiceType::find($newServiceTypeId)?->name
                : null;
            $lower = mb_strtolower((string) $serviceTypeName);
            if (str_contains($lower, 'интернет') || str_contains($lower, 'inet')) {
                $newPrefix = 'i';
            } elseif (str_contains($lower, 'ктв') || str_contains($lower, 'ctv') || str_contains($lower, 'кабел')) {
                $newPrefix = 'c';
            } else {
                $newPrefix = 'Т';
            }
            if (preg_match('/^[^-]+-(\d+)$/', $ticket->number, $m)) {
                $data['number'] = $newPrefix . '-' . $m[1];
            }
        }

        $ticket->update($data);
        return back()->with('success', 'Заявка обновлена');
    }

    public function destroy(Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();
        return redirect()->route('tickets.index')->with('success', 'Заявка удалена');
    }

    // === Действия по заявке ===

    public function start(Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('start', $ticket);
        $this->ticketService->updateStatus($ticket, 'in_progress', auth()->user());
        return back()->with('success', 'Заявка взята в работу');
    }

    public function pause(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('pause', $ticket);
        $this->ticketService->updateStatus($ticket, 'paused', auth()->user(), $request->comment);
        return back()->with('success', 'Заявка приостановлена');
    }

    public function close(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('close', $ticket);
        $request->validate([
            'comment'  => 'nullable|string|max:2000',
            'act_type' => 'nullable|in:regular,repair',
        ]);

        $materialsData = $request->input('materials');
        if (is_string($materialsData)) {
            $materialsData = json_decode($materialsData, true) ?? [];
        }

        if (!empty($materialsData) && empty($request->act_type)) {
            return back()->withErrors(['act_type' => 'При использовании материалов обязателен тип акта.'])->withInput();
        }

        // attempts=3: см. Act::createWithGeneratedNumber() — конкурентное закрытие
        // с тем же префиксом номера акта может словить deadlock на lockForUpdate(),
        // Laravel в этом случае полностью переиграет транзакцию.
        \Illuminate\Support\Facades\DB::transaction(function () use ($ticket, $materialsData, $request) {
            $this->ticketService->updateStatus($ticket, 'closed', auth()->user(), $request->comment);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->ticketService->storeAttachment($ticket, $file, auth()->user(), 'close');
                }
            }

            // Материалы теперь формируют Акт (Act + ActMaterial), а не пишутся на тикет напрямую —
            // см. фичу "Акты" (согласование Бригадир -> ПЭО/Логистика -> Абонотдел).
            if (!empty($materialsData) && is_array($materialsData)) {
                $act = \App\Models\Act::createWithGeneratedNumber([
                    'ticket_id'  => $ticket->id,
                    'type'       => $request->act_type,
                    'status'     => 'pending_foreman',
                    'created_by' => auth()->id(),
                ], fn() => \App\Models\Act::generateNumber($ticket, $request->act_type));

                foreach ($materialsData as $item) {
                    if (empty($item['material_id']) || empty($item['quantity'])) continue;
                    $material = \App\Models\Material::find($item['material_id']);
                    if ($material) {
                        $act->materials()->create([
                            'material_id'   => $material->id,
                            'material_name' => $material->name,
                            'material_code' => $material->code,
                            'material_unit' => $material->unit,
                            'price_at_time' => $material->price,
                            'quantity'      => $item['quantity'],
                            'created_by'    => auth()->id(),
                        ]);
                    }
                }

                $act->history()->create([
                    'user_id' => auth()->id(),
                    'action'  => 'created',
                ]);
            }
        }, 3);

        return back()->with('success', 'Заявка закрыта');
    }

    public function freeSlot(Request $request): \Illuminate\Http\JsonResponse
    {
        $workStart     = SystemSetting::get('work_hours_start', '09:00');
        $workEnd       = SystemSetting::get('work_hours_end',   '17:00');
        $step          = (int) SystemSetting::get('schedule_step_minutes', 30);
        $brigadeId     = $request->integer('brigade_id') ?: null;
        $serviceTypeId = $request->integer('service_type_id') ?: null;

        [$sh, $sm] = array_map('intval', explode(':', $workStart));
        [$eh, $em] = array_map('intval', explode(':', $workEnd));
        $startMins = $sh * 60 + $sm;
        $endMins   = $eh * 60 + $em;
        // Поиск стартует с переданной даты (например, когда пользователь уже выбрал день
        // вручную и слот оказался занят) — по умолчанию, как и раньше, с сегодня.
        $day = $request->filled('date') ? \Carbon\Carbon::parse($request->date)->startOfDay() : today();
        $nowMins = now()->hour * 60 + now()->minute;

        for ($attempt = 0; $attempt < 60; $attempt++, $day->addDay()) {
            // Для сегодняшнего дня начинаем с ближайшего будущего слота, для остальных — с начала дня.
            // Слот обязательно выравниваем по сетке шага от $startMins — иначе "ближайшее будущее
            // время" (текущие часы:минуты + шаг) не совпадёт ни с одним <option> в TimePicker
            // (тот строит список только от рабочего начала дня кратно шагу), и выбор в интерфейсе
            // будет выглядеть пустым, хотя значение технически подставлено.
            if ($day->isToday()) {
                $raw = max($startMins, $nowMins + $step);
                $fromMins = $startMins + (int) ceil(($raw - $startMins) / $step) * $step;
            } else {
                $fromMins = $startMins;
            }

            if ($fromMins > $endMins) continue;

            // Те же условия, что и в TicketService::checkSlotConflict — иначе "свободный"
            // слот может тут же оказаться занятым при реальной проверке на сохранении.
            $occupied = Ticket::whereDate('scheduled_at', $day->toDateString())
                ->when($brigadeId, fn($q) => $q->where('brigade_id', $brigadeId))
                ->whereNotNull('scheduled_at')
                ->whereHas('status', fn($q) => $q->where('is_final', false))
                ->when($serviceTypeId, fn($q) => $q->where('service_type_id', $serviceTypeId))
                ->pluck('scheduled_at')
                ->mapWithKeys(fn($dt) => [\Carbon\Carbon::parse($dt)->format('H:i') => true]);

            for ($m = $fromMins; $m <= $endMins; $m += $step) {
                $slot = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                if (!$occupied->has($slot)) {
                    return response()->json(['datetime' => $day->format('Y-m-d') . 'T' . $slot]);
                }
            }
        }

        return response()->json(['datetime' => today()->addDay()->format('Y-m-d') . 'T' . $workStart]);
    }

    public function postpone(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('postpone', $ticket);
        $request->validate([
            'scheduled_at' => 'required|date',
            'comment'      => 'nullable|string|max:2000',
        ]);

        $ticket->update(['scheduled_at' => $request->scheduled_at]);
        $this->ticketService->updateStatus($ticket, 'postponed', auth()->user(), $request->comment);

        return back()->with('success', 'Заявка перенесена на ' . \Carbon\Carbon::parse($request->scheduled_at)->format('d.m.Y H:i'));
    }

    public function bulkClose(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'ids'        => 'required|array|min:1|max:500',
            'ids.*'      => 'integer|exists:tickets,id',
            'comment'    => 'nullable|string|max:2000',
            'act_number' => 'nullable|string|max:50',
        ]);

        $actNumber = filled($request->act_number) ? $request->act_number : 'б/а';
        $tickets   = Ticket::findMany($request->ids);
        $user      = auth()->user();

        foreach ($tickets as $ticket) {
            if ($user->cannot('close', $ticket)) continue;
            \Illuminate\Support\Facades\DB::transaction(function () use ($ticket, $actNumber, $request, $user) {
                $ticket->update(['act_number' => $actNumber]);
                $this->ticketService->updateStatus($ticket, 'closed', $user, $request->comment);
            });
        }

        return response()->json(['ok' => true, 'count' => $tickets->count()]);
    }

    public function bulkReschedule(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'ids'          => 'required|array|min:1|max:500',
            'ids.*'        => 'integer|exists:tickets,id',
            'scheduled_at' => 'required|date',
            'comment'      => 'nullable|string|max:2000',
        ]);

        $tickets = Ticket::findMany($request->ids);
        $user    = auth()->user();

        foreach ($tickets as $ticket) {
            if ($user->cannot('postpone', $ticket)) continue;
            $ticket->update(['scheduled_at' => $request->scheduled_at]);
            $this->ticketService->updateStatus($ticket, 'postponed', $user, $request->comment);
        }

        return response()->json(['ok' => true, 'count' => $tickets->count()]);
    }

    public function reopen(Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $ticket);
        $this->ticketService->updateStatus($ticket, 'new', auth()->user());
        return back()->with('success', 'Заявка переоткрыта');
    }

    public function assign(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('assign', $ticket);
        $request->validate([
            'brigade_id' => 'nullable|exists:brigades,id',
            'user_id'    => 'nullable|exists:users,id',
        ]);
        $this->ticketService->assign($ticket, $request->brigade_id, $request->user_id, auth()->user());
        return back()->with('success', 'Бригада назначена');
    }


    public function map(): Response
    {
        $this->authorize('viewAny', Ticket::class);
        return Inertia::render('Tickets/Map');
    }

    public function mapData(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);
        $period = $request->get('period', 'week');
        $from = match($period) {
            'today' => now()->startOfDay(),
            'week'  => now()->subWeek()->startOfDay(),
            'month' => now()->subMonth()->startOfDay(),
            default => now()->subWeek()->startOfDay(),
        };

        $points = Ticket::query()
            ->join('addresses', 'tickets.address_id', '=', 'addresses.id')
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->join('ticket_types', 'tickets.type_id', '=', 'ticket_types.id')
            ->whereNotNull('addresses.lat')
            ->whereNotNull('addresses.lng')
            ->where('tickets.created_at', '>=', $from)
            ->select([
                'tickets.id',
                'tickets.number',
                'tickets.created_at',
                'ticket_statuses.name as status_name',
                'ticket_types.name as type_name',
                'addresses.lat',
                'addresses.lng',
                'addresses.city',
                'addresses.street',
                'addresses.building',
                'addresses.apartment',
            ])
            ->orderBy('tickets.created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id'     => $t->id,
                'num'    => $t->number,
                'lat'    => (float) $t->lat,
                'lng'    => (float) $t->lng,
                'addr'   => implode(', ', array_filter([$t->city, $t->street, $t->building, $t->apartment ? 'кв.'.$t->apartment : null])),
                'status' => $t->status_name,
                'type'   => $t->type_name,
                'date'   => \Carbon\Carbon::parse($t->created_at)->format('d.m.Y'),
            ]);

        return response()->json($points);
    }

    public function addComment(AddCommentRequest $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('comment', $ticket);

        $comment = $ticket->comments()->create([
            'user_id'     => auth()->id(),
            'body'        => $request->body,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, auth()->user(), 'comment', $comment->id);
            }
        }

        return back()->with('success', 'Комментарий добавлен');
    }
}
