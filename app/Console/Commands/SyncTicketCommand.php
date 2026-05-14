<?php
namespace App\Console\Commands;

use App\Models\{Ticket, Brigade, SystemSetting};
use App\Services\TicketService;
use App\Notifications\NewTicketNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncTicketCommand extends Command
{
    protected $signature   = 'sync:ticket {payload}';
    protected $description = 'Create ticket from sync script (bypasses HTTP)';

    public function __construct(private TicketService $ticketService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $data = json_decode($this->argument('payload'), true);

        if (($data['token'] ?? '') !== config('services.sync.token')) {
            $this->line(json_encode(['error' => 'Unauthorized']));
            return 1;
        }

        $oldNumber = 'old-' . $data['old_id'];
        if (Ticket::where('number', $oldNumber)->exists()) {
            $this->line(json_encode(['status' => 'already_synced']));
            return 0;
        }

        $brigade = Brigade::where('name', 'ЧГДН')->first();
        if (!$brigade) {
            $this->line(json_encode(['error' => 'Brigade ЧГДН not found']));
            return 1;
        }

        $scheduledAt = $this->findNextSlot($brigade->id, $data['execution_date']);
        $creator = \App\Models\User::find($data['creator_id'] ?? 1) ?? \App\Models\User::first();

        $ticket = $this->ticketService->create([
            'number'          => $oldNumber,
            'address_id'      => $data['address_id'],
            'apartment'       => $data['apartment'] ?: null,
            'type_id'         => $data['type_id'],
            'service_type_id' => $data['service_type_id'],
            'brigade_id'      => $brigade->id,
            'phone'           => $data['phone'],
            'description'     => $data['description'] ?? '',
            'scheduled_at'    => $scheduledAt,
            'priority'        => 'normal',
        ], $creator);

        NewTicketNotification::dispatch($ticket->fresh(['address']));

        $this->line(json_encode([
            'status'       => 'created',
            'id'           => $ticket->id,
            'number'       => $ticket->number,
            'scheduled_at' => $scheduledAt,
        ]));
        return 0;
    }

    private function findNextSlot(int $brigadeId, string $date): string
    {
        $step      = (int) SystemSetting::get('schedule_step_minutes', 30);
        $workStart = SystemSetting::get('work_hours_start', '09:00');
        $workEnd   = SystemSetting::get('work_hours_end', '17:00');
        [$sh, $sm] = array_map('intval', explode(':', $workStart));
        [$eh, $em] = array_map('intval', explode(':', $workEnd));
        $startMins = $sh * 60 + $sm;
        $endMins   = $eh * 60 + $em;

        $occupied = Ticket::whereDate('scheduled_at', $date)
            ->where('brigade_id', $brigadeId)
            ->whereNotNull('scheduled_at')
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