<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketType, TicketStatus, Brigade, Address, User, ServiceType, SystemSetting};
use App\Services\TicketService;
use App\Http\Requests\{StoreTicketRequest, UpdateTicketRequest, AddCommentRequest};
use Illuminate\Http\Request;
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
            ->when($request->overdue, fn($q) => $q->whereIn('status_id',
                \App\Models\TicketStatus::where('is_final', false)->pluck('id'))
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<', now()))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->when($request->date_from, fn($q) => $q->where('scheduled_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('scheduled_at', '<=', $request->date_to . ' 23:59:59'))
->when($request->input('closed_today'), function ($q) use ($request) {
                $closedId = \App\Models\TicketStatus::where('slug', 'closed')->value('id');
                $q->where('status_id', $closedId)->whereDate('closed_at', today());
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

            ->orderBy($sort, $sortDir)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets'  => $tickets,
            'filters'  => $request->only(['search', 'status', 'type', 'brigade', 'priority', 'date_from', 'date_to', 'address_id', 'city', 'street', 'building', 'service_type', 'overdue', 'closed_today', 'sort', 'sortDir']),
            'statuses' => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'types'    => TicketType::active()->get(['id', 'name', 'color']),
            'brigades'     => Brigade::orderBy('name')->get(['id', 'name']),
            'serviceTypes' => \App\Models\ServiceType::active()->get(['id', 'name', 'color']),
            'overdueCount' => Ticket::whereIn('status_id',
                \App\Models\TicketStatus::where('is_final', false)->pluck('id'))
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<', now())
                ->count(),
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
            'materials',
            'history.user',
        ]);

        // История заявок по этому адресу (кроме текущей)
        $addressHistory = $ticket->address_id

            ? Ticket::with(['type', 'status'])
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
                       'apartment', 'description', 'close_notes', 'act_number', 'created_at'])
            : [];

        return Inertia::render('Tickets/Show', [
            'ticket'         => $ticket,
            'addressHistory' => $addressHistory,
            'materialsCatalog' => \App\Models\Material::active()->orderBy('sort_order')->orderBy('name')->get(['id','name','unit','price']),
            'statuses'       => TicketStatus::active()->get(['id', 'name', 'color', 'slug', 'is_final']),
            'brigades'       => Brigade::with('members')->orderBy('name')->get(),
            'canEdit'        => auth()->user()->can('update', $ticket),
            'canAssign'      => auth()->user()->can('assign', $ticket),
            'canClose'       => auth()->user()->can('close', $ticket),
            'canComment'     => auth()->user()->can('comment', $ticket),
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
            'ticket'   => $ticket,
            'types'    => TicketType::active()->get(['id', 'name', 'color']),
            'statuses' => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'brigades' => Brigade::with('members')->orderBy('name')->get(),
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
        $ticket->update($request->validated());
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
            'comment'    => 'nullable|string|max:2000',
            'act_number' => 'nullable|string|max:50',
        ]);

        // Если акт не указан — ставим б/а
        $actNumber = $request->filled('act_number') ? $request->act_number : 'б/а';
        $ticket->update(['act_number' => $actNumber]);

        $this->ticketService->updateStatus($ticket, 'closed', auth()->user(), $request->comment);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, auth()->user(), 'close');
            }
        }

        // Сохраняем расходные материалы
        $materialsData = $request->input('materials');
        if (is_string($materialsData)) {
            $materialsData = json_decode($materialsData, true) ?? [];
        }
        if (!empty($materialsData) && is_array($materialsData)) {
            $ticket->materials()->delete();
            foreach ($materialsData as $item) {
                if (empty($item['material_id']) || empty($item['quantity'])) continue;
                $material = \App\Models\Material::find($item['material_id']);
                if ($material) {
                    $ticket->materials()->create([
                        'material_id'   => $material->id,
                        'material_name' => $material->name,
                        'material_unit' => $material->unit,
                        'price_at_time' => $material->price,
                        'quantity'      => $item['quantity'],
                        'created_by'    => auth()->id(),
                    ]);
                }
            }
        }

        return back()->with('success', 'Заявка закрыта');
    }

    public function postpone(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $ticket);
        $request->validate([
            'scheduled_at' => 'required|date',
            'comment'      => 'nullable|string|max:2000',
        ]);

        $ticket->update(['scheduled_at' => $request->scheduled_at]);
        $this->ticketService->updateStatus($ticket, 'postponed', auth()->user(), $request->comment);

        return back()->with('success', 'Заявка перенесена на ' . \Carbon\Carbon::parse($request->scheduled_at)->format('d.m.Y H:i'));
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
