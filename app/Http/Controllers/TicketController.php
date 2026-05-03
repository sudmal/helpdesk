<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketType, TicketStatus, Brigade, Address, User};
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

        $tickets = Ticket::with(['address', 'type', 'status', 'brigade', 'creator', 'assignee'])
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->status, fn($q) => $q->where('status_id', $request->status))
            ->when($request->type,   fn($q) => $q->where('type_id', $request->type))
            ->when($request->brigade, fn($q) => $q->where('brigade_id', $request->brigade))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->when($request->date_from, fn($q) => $q->where('scheduled_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('scheduled_at', '<=', $request->date_to . ' 23:59:59'))
            ->when(
                // Монтажник видит только свои заявки
                auth()->user()->isTechnician(),
                fn($q) => $q->where(function ($sub) {
                    $sub->where('assigned_to', auth()->id())
                        ->orWhereHas('brigade', fn($b) => $b->whereHas('members', fn($m) => $m->where('user_id', auth()->id())));
                })
            )
            ->latest('scheduled_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets'  => $tickets,
            'filters'  => $request->only(['search', 'status', 'type', 'brigade', 'priority', 'date_from', 'date_to']),
            'statuses' => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'types'    => TicketType::active()->get(['id', 'name', 'color']),
            'brigades' => Brigade::orderBy('name')->get(['id', 'name']),
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
                ->take(10)
                ->get();
        }

        return Inertia::render('Tickets/Create', [
            'types'    => TicketType::active()->get(['id', 'name', 'color']),
            'statuses' => TicketStatus::active()->get(['id', 'name', 'color', 'slug']),
            'brigades' => Brigade::with('territories')->orderBy('name')->get(),
            'address'  => $address,
            'addressHistory' => $addressHistory,
        ]);
    }

    public function store(StoreTicketRequest $request): \Illuminate\Http\RedirectResponse
    {
        $ticket = $this->ticketService->create($request->validated(), auth()->user());
        return redirect()->route('tickets.show', $ticket)->with('success', 'Заявка создана');
    }

    public function show(Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'address.territory',
            'type', 'status', 'brigade.members', 'creator', 'assignee',
            'comments.author', 'comments.attachments',
            'attachments.uploader',
            'history.user',
        ]);

        // История заявок по этому адресу (кроме текущей)
        $addressHistory = $ticket->address_id
            ? Ticket::with(['type', 'status'])
                ->where('address_id', $ticket->address_id)
                ->where('id', '!=', $ticket->id)
                ->latest()
                ->take(5)
                ->get()
            : [];

        return Inertia::render('Tickets/Show', [
            'ticket'         => $ticket,
            'addressHistory' => $addressHistory,
            'statuses'       => TicketStatus::active()->get(['id', 'name', 'color', 'slug', 'is_final']),
            'brigades'       => Brigade::with('members')->orderBy('name')->get(),
            'canEdit'        => auth()->user()->can('update', $ticket),
            'canAssign'      => auth()->user()->can('assign', $ticket),
            'canClose'       => auth()->user()->can('close', $ticket),
            'canComment'     => auth()->user()->can('comment', $ticket),
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
        $request->validate(['comment' => 'nullable|string|max:2000']);
        $this->ticketService->updateStatus($ticket, 'closed', auth()->user(), $request->comment);

        // Вложения при закрытии
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, auth()->user(), 'close');
            }
        }

        return back()->with('success', 'Заявка закрыта');
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
