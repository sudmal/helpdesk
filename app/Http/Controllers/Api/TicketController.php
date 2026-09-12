<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\ConnectionRequest;
use App\Models\Material;
use App\Models\Promotion;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        // Единая формула видимости (2026-08-04) — 1:1 совпадает с веб-порталом
        // (TicketController::index()): territoryScopeIds() для всех, кроме
        // admin (отдельный хардкод-байпас). Раньше здесь была своя, отдельная
        // от веба логика (фильтр только по ticket.brigade_id) — бригадир видел
        // в приложении только заявки, буквально назначенные его бригаде, хотя
        // по чекбоксам территорий должен был видеть больше. Пустой список
        // территорий (бригадир/монтажник без бригады и территорий) → 0 заявок,
        // не «видно всё» — пустой whereIn() корректно даёт 0 строк.
        $scopeToTerritory = !$user->isAdmin();
        $territoryIds     = $scopeToTerritory ? $user->territoryScopeIds() : collect();

        $base = fn(): Builder => Ticket::with([
                'address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee',
                'comments.author', 'comments.attachments', 'attachments', 'act',
            ])
            ->when($scopeToTerritory, fn($q) =>
                $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $territoryIds))
            );

        $overdue  = $base()->whereDate('scheduled_at', '<', $today)
                           ->whereHas('status', fn($s) => $s->where('is_final', false))
                           ->orderBy('scheduled_at')->get();

        $today_list = $base()->whereDate('scheduled_at', $today)
                             ->orderBy('scheduled_at')->get();

        $new_today  = $base()->whereDate('scheduled_at', $today)
                             ->whereDate('created_at', $today)
                             ->orderByDesc('created_at')->get();

        $tomorrow_list = $base()->whereDate('scheduled_at', $tomorrow)
                                ->orderBy('scheduled_at')->get();

        // Заявки на подключение, назначенные (status=scheduled) на те же дни --
        // раньше в мобильном API отсутствовали вообще, из-за чего монтажник видел
        // их только на отдельном экране "Подключения", а не среди обычных заявок
        // дня (расхождение с веб-дашбордом, где они давно слиты в общий список
        // по времени, см. DashboardController::index()). См. ТЗ в API_MOBILE.md,
        // раздел "Подключения в списке заявок дня" -- тот же баг был на PWA и
        // в Android (там маскировался баннером-счётчиком, но сами заявки в
        // список всё равно не попадали).
        $connBase = fn(): Builder => ConnectionRequest::with(['territory', 'serviceType', 'act'])
            ->where('status', 'scheduled')
            ->when($scopeToTerritory, fn($q) => $q->whereIn('territory_id', $territoryIds));

        $overdue_connections  = $connBase()->whereDate('scheduled_at', '<', $today)
                                            ->orderBy('scheduled_at')->get();
        $today_connections    = $connBase()->whereDate('scheduled_at', $today)
                                            ->orderBy('scheduled_at')->get();
        $tomorrow_connections = $connBase()->whereDate('scheduled_at', $tomorrow)
                                            ->orderBy('scheduled_at')->get();

        return response()->json([
            'overdue'             => $this->format($overdue),
            'today'               => $this->format($today_list),
            'new_today'           => $this->format($new_today),
            'tomorrow'            => $this->format($tomorrow_list),
            'overdue_connections'  => $this->formatConnections($overdue_connections),
            'today_connections'    => $this->formatConnections($today_connections),
            'tomorrow_connections' => $this->formatConnections($tomorrow_connections),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->load(['address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'closedBy', 'comments.author', 'comments.attachments', 'attachments', 'act']);

        return response()->json($this->formatOne($ticket));
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'body'          => 'nullable|string|max:2000',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        if (!$request->filled('body') && !$request->hasFile('attachments')) {
            return response()->json(['error' => 'body or attachments required'], 422);
        }

        $comment = $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->input('body', ''),
            'is_internal' => false,
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $att = $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'comment', $comment->id);
                $attachments[] = [
                    'id'            => $att->id,
                    'original_name' => $att->original_name,
                    'url'           => Storage::url($att->stored_path),
                    'mime_type'     => $att->mime_type,
                    'size'          => $att->size,
                ];
            }
        }

        $comment->load('author');

        return response()->json([
            'id'          => $comment->id,
            'body'        => $comment->body,
            'author'      => $comment->author?->name,
            'created_at'  => $comment->created_at->toIso8601String(),
            'attachments' => $attachments,
        ], 201);
    }

    public function addAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $request->validate([
            'attachments'   => 'required|array|min:1|max:10',
            'attachments.*' => 'required|file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        $stored = [];
        foreach ($request->file('attachments') as $file) {
            $att = $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'attachment');
            $stored[] = [
                'id'            => $att->id,
                'original_name' => $att->original_name,
                'url'           => Storage::url($att->stored_path),
                'mime_type'     => $att->mime_type,
                'size'          => $att->size,
            ];
        }

        return response()->json(['attachments' => $stored], 201);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $request->validate([
            'close_notes'   => 'nullable|string|max:2000',
            'act_type'      => [
                'nullable', 'in:regular,repair',
                function ($attribute, $value, $fail) use ($request) {
                    if (!empty($request->input('materials')) && empty($value)) {
                        $fail('При использовании материалов обязателен тип акта.');
                    }
                },
            ],
            'materials'     => 'nullable|array',
            'materials.*.material_id' => 'required|integer|exists:materials,id',
            'materials.*.quantity'    => 'required|numeric|min:0.01',
            'promotion_id'  => [
                'nullable', 'integer', 'exists:promotions,id',
                function ($attribute, $value, $fail) use ($request, $ticket) {
                    if (!$value) return;
                    if (empty($request->input('materials'))) {
                        $fail('Акция применяется только вместе с материалами (нужен акт).');
                    }
                    if (!$ticket->type?->allows_promotion) {
                        $fail('Акция недоступна для этого типа заявки.');
                    }
                    if ($request->act_type !== 'regular') {
                        $fail('Акция доступна только для обычного типа акта.');
                    }
                },
            ],
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        // Заявку могли переоткрыть и закрыть повторно — у неё уже есть акт
        // (acts.ticket_id уникален). Повторной отправкой материалов акт больше
        // не пересоздаём — состав уже созданного акта правится на его странице
        // (см. тот же фикс в веб-TicketController::close()).
        if (!empty($request->materials) && $ticket->act) {
            return response()->json([
                'message' => "У заявки уже есть акт №{$ticket->act->number}. Изменить список материалов можно в разделе Акты.",
                'errors'  => ['materials' => ["У заявки уже есть акт №{$ticket->act->number}."]],
            ], 422);
        }

        // attempts=3: если материалы формируют Акт с автогенерируемым номером,
        // конкурентное закрытие другой заявки с тем же префиксом может словить
        // deadlock на lockForUpdate() внутри Act::createWithGeneratedNumber()
        // (см. память project-acts-feature) — Laravel в этом случае полностью
        // переиграет транзакцию, а не просто прокинет ошибку наверх.
        DB::transaction(function () use ($ticket, $request) {
            $this->ticketService->updateStatus($ticket, 'closed', $request->user(), $request->close_notes);

            // Материалы формируют Акт (Act + ActMaterial) — см. фичу "Акты".
            if (!empty($request->materials)) {
                $promotion = $request->filled('promotion_id') ? Promotion::find($request->promotion_id) : null;

                $act = Act::createWithGeneratedNumber([
                    'ticket_id'       => $ticket->id,
                    'type'            => $request->act_type,
                    'status'          => 'pending_foreman',
                    'created_by'      => $request->user()->id,
                    'promotion_id'    => $promotion?->id,
                    'promotion_name'  => $promotion?->name,
                    'promotion_price' => $promotion?->price,
                    // 2026-09-12: акт больше не генерирует свой номер — берёт
                    // готовый номер заявки (см. Ticket::generateNumber() и
                    // тот же фикс в веб-TicketController::close()).
                ], fn() => $ticket->number);

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

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'attachment');
            }
        }

        $ticket->load(['address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'closedBy', 'comments.author', 'attachments', 'act']);

        return response()->json($this->formatOne($ticket));
    }

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $this->ticketService->updateStatus($ticket, 'new', $request->user());

        $ticket->load(['address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'closedBy', 'comments.author', 'comments.attachments', 'attachments', 'act']);

        return response()->json($this->formatOne($ticket));
    }

    public function reschedule(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('postpone', $ticket);

        $request->validate([
            'scheduled_at' => 'required|date|after:today',
            'comment'      => 'nullable|string|max:2000',
        ]);

        $ticket->update(['scheduled_at' => $request->scheduled_at]);
        $this->ticketService->updateStatus($ticket, 'postponed', $request->user(), $request->comment);

        $ticket->load(['address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'closedBy', 'comments.author', 'comments.attachments', 'attachments', 'act']);

        return response()->json($this->formatOne($ticket));
    }

    /**
     * Отмена заявки -- то же действие, что и на веб-портале (см.
     * TicketController::cancel(), политика TicketPolicy::cancel()): та же
     * зона ответственности, что и закрытие (tickets.close), причина
     * обязательна и попадает в close_notes + отдельную строку истории.
     */
    public function cancel(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('cancel', $ticket);

        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $this->ticketService->updateStatus($ticket, 'cancelled', $request->user(), $request->comment);

        $ticket->history()->create([
            'user_id'   => $request->user()->id,
            'action'    => 'cancelled',
            'new_value' => $request->comment,
        ]);

        $ticket->load(['address.territory', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'closedBy', 'comments.author', 'attachments', 'act']);

        return response()->json($this->formatOne($ticket));
    }

    private function formatOne(Ticket $t): array
    {
        return [
            'id'           => $t->id,
            'number'       => $t->number,
            'scheduled_at' => $t->scheduled_at?->toIso8601String(),
            'closed_at'    => $t->closed_at?->toIso8601String(),
            'closed_by'    => $t->closedBy?->name,
            'description'  => $t->description,
            'phone'        => $t->phone,
            'apartment'    => $t->apartment,
            'close_notes'  => $t->close_notes,
            'act_number'   => $t->act?->number,
            'act'          => $t->act ? [
                'id'                   => $t->act->id,
                'number'               => $t->act->number,
                'type'                 => $t->act->type,
                'status'               => $t->act->status,
                'materials_changed_at' => $t->act->materials_changed_at?->toIso8601String(),
                'promotion_id'         => $t->act->promotion_id,
                'promotion_name'       => $t->act->promotion_name,
                'promotion_price'      => $t->act->promotion_price,
            ] : null,
            'address'      => $t->address ? [
                // Без квартиры: apartment этой заявки уже есть отдельным полем выше,
                // мобильное приложение само дописывает его к адресу. Квартира в
                // Address — это общий адрес дома, может относиться к другой заявке.
                'full'     => collect([
                    $t->address->city,
                    $t->address->street,
                    $t->address->building,
                ])->filter()->implode(', '),
                'street'   => $t->address->street,
                'building' => $t->address->building,
            ] : null,
            // territory заявки берётся от address.territory_id (2026-08-04, по
            // запросу Android-агента — quick-фильтр по территории на вкладках
            // заявок, по аналогии с ConnectionRequest.territory). Не через
            // бригаду — бригада заявки и территория адреса могут расходиться
            // (см. память project-territory-visibility-system), адрес надёжнее.
            'territory' => $t->address?->territory ? [
                'id'   => $t->address->territory->id,
                'name' => $t->address->territory->name,
            ] : null,
            'type'    => $t->type?->name,
            // Новое поле (акции на обычных заявках, 2026-08-11) — намеренно
            // ДОБАВЛЕНО рядом с уже существующим 'type' (строка), а не вместо
            // него, чтобы не ломать текущий контракт для ещё не обновлённого
            // Android-клиента. См. ТЗ для мобильного агента.
            'type_allows_promotion' => (bool) $t->type?->allows_promotion,
            'service_type' => $t->serviceType ? [
                'id'    => $t->serviceType->id,
                'name'  => $t->serviceType->name,
                'color' => $t->serviceType->color,
            ] : null,
            'can' => [
                'reopen' => auth()->user()?->can('update', $t) ?? false,
            ],
            'status'  => [
                'name'       => $t->status?->name,
                'is_final'   => (bool) $t->status?->is_final,
                'color'      => $t->status?->color,
                'slug'       => $t->status?->slug,
                // Порядок группировки статусов -- новое поле (сортировка
                // Дашборда "по статусу", 2026-09-05), см. ТЗ в API_MOBILE.md.
                'sort_order' => $t->status?->sort_order,
            ],
            'brigade'  => $t->brigade?->name,
            'assignee' => $t->assignee?->name,
            'comments' => $t->comments->map(fn($c) => [
                'id'          => $c->id,
                'body'        => $c->body,
                'author'      => $c->author?->name,
                'created_at'  => $c->created_at->toIso8601String(),
                'attachments' => ($c->relationLoaded('attachments') ? $c->attachments : collect())->map(fn($a) => [
                    'id'            => $a->id,
                    'original_name' => $a->original_name,
                    'url'           => Storage::url($a->stored_path),
                    'mime_type'     => $a->mime_type,
                    'size'          => $a->size,
                ])->values()->all(),
            ])->values()->all(),
            'attachments' => ($t->relationLoaded('attachments') ? $t->attachments : collect())->map(fn($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => Storage::url($a->stored_path),
                'mime_type'     => $a->mime_type,
                'size'          => $a->size,
            ])->values()->all(),
        ];
    }

    private function format($tickets): array
    {
        return $tickets->map(fn(Ticket $t) => $this->formatOne($t))->values()->all();
    }

    // Та же форма полей, что у territory/service_type в formatOne() выше --
    // сделано намеренно одинаково, чтобы клиент мог сортировать/фильтровать
    // тикеты и подключения одним и тем же кодом без спецразбора по типу.
    private function formatConnections($connections): array
    {
        return $connections->map(fn(ConnectionRequest $r) => [
            'id'             => $r->id,
            'name'           => $r->name,
            'phone'          => $r->phone,
            'address_string' => $r->address_string,
            'description'    => $r->description,
            'status'         => $r->status,
            'scheduled_at'   => $r->scheduled_at?->toIso8601String(),
            'created_at'     => $r->created_at->toIso8601String(),
            'territory'      => $r->territory ? ['id' => $r->territory->id, 'name' => $r->territory->name] : null,
            'service_type'   => $r->serviceType ? [
                'id'    => $r->serviceType->id,
                'name'  => $r->serviceType->name,
                'color' => $r->serviceType->color,
            ] : null,
            'act'         => $r->act ? ['materials_changed_at' => $r->act->materials_changed_at?->toIso8601String()] : null,
            'feasibility' => $r->feasibility,
        ])->values()->all();
    }
}