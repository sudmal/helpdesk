<?php
namespace App\Http\Controllers;
use App\Models\{Ticket, Brigade, SystemSetting};
use App\Services\TicketService;
use App\Notifications\NewTicketNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function store(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== config('services.sync.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $oldNumber = 'old-' . $request->input('old_id');
        if (Ticket::where('number', $oldNumber)->exists()) {
            return response()->json(['status' => 'already_synced']);
        }
        $brigade = Brigade::where('name', 'ЧГДН')->first();
        if (!$brigade) {
            return response()->json(['error' => 'Brigade ЧГДН not found'], 422);
        }
        $date = $request->input('execution_date'); // 'Y-m-d'
        $scheduledAt = $this->findNextSlot($brigade->id, $date);
        $creator = \App\Models\User::find($request->input('creator_id', 1))
            ?? \App\Models\User::first();
        $ticket = $this->ticketService->create([
            'number'          => $oldNumber,
            'address_id'      => $request->input('address_id'),
            'apartment'       => $request->input('apartment') ?: null,
            'type_id'         => $request->input('type_id'),
            'service_type_id' => $request->input('service_type_id'),
            'brigade_id'      => $brigade->id,
            'phone'           => $request->input('phone'),
            'description'     => $request->input('description', ''),
            'scheduled_at'    => $scheduledAt,
            'priority'        => 'normal',
        ], $creator);
        NewTicketNotification::dispatch($ticket->fresh(['address']));
        return response()->json([
            'status' => 'created', 'id' => $ticket->id,
            'number' => $ticket->number, 'scheduled_at' => $scheduledAt,
        ], 201);
    }

    private function findNextSlot(int $brigadeId, string $date): string
    {
        $step = (int) SystemSetting::get('schedule_step_minutes', 30);
        $workStart = SystemSetting::get('work_hours_start', '09:00');
        $workEnd   = SystemSetting::get('work_hours_end', '17:00');
        [$sh, $sm] = array_map('intval', explode(':', $workStart));
        [$eh, $em] = array_map('intval', explode(':', $workEnd));
        $startMins = $sh * 60 + $sm;
        $endMins   = $eh * 60 + $em;
        $occupied = Ticket::whereDate('scheduled_at', $date)
            ->where('brigade_id', $brigadeId)->whereNotNull('scheduled_at')
            ->pluck('scheduled_at')
            ->mapWithKeys(fn($dt) => [Carbon::parse($dt)->format('H:i') => true]);
        for ($m = $startMins; $m <= $endMins; $m += $step) {
            $slot = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            if (!$occupied->has($slot)) {
                return $date . ' ' . $slot . ':00';
            }
        }
        $overflow = $endMins + $step;
        return $date . ' ' . sprintf('%02d:%02d:00', intdiv($overflow, 60), $overflow % 60);
    }
}