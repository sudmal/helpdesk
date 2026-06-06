<?php

namespace App\Console\Commands;

use App\Models\Call;
use Illuminate\Console\Command;

class PruneUnresolvedCalls extends Command
{
    protected $signature   = 'helpdesk:prune-calls {--days=2 : Удалять звонки старше N дней}';
    protected $description = 'Удалить из базы звонки без адреса из биллинга старше N дней';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $deleted = Call::whereNull('address_string')
            ->where('called_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Удалено звонков без адреса: {$deleted}");
        return 0;
    }
}
