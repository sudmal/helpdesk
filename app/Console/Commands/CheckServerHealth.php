<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HealthAlertNotification;
use App\Services\HealthReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckServerHealth extends Command
{
    protected $signature   = 'helpdesk:check-health';
    protected $description = 'Проверка здоровья сервера (диск/SMART/CPU/RAM/сервисы), алерт при аномалиях';

    // Уведомляем только при изменении набора проблем (появились/исчезли),
    // не долбим одним и тем же алертом каждый прогон планировщика.
    private const CACHE_KEY = 'health:last_anomalies';

    public function handle(HealthReportService $service): int
    {
        $report  = $service->collect();
        $issues  = $service->evaluateAnomalies($report);
        $prev    = Cache::get(self::CACHE_KEY, []);

        sort($issues);
        $prevSorted = $prev;
        sort($prevSorted);

        if ($issues === $prevSorted) {
            return Command::SUCCESS;
        }

        Cache::put(self::CACHE_KEY, $issues, now()->addDays(7));

        $resolved   = empty($issues) && !empty($prev);
        $recipients = User::whereIn('login', ['admin', 'das'])->where('is_active', true)->get();

        foreach ($recipients as $user) {
            $user->notify(new HealthAlertNotification($issues ?: $prev, $resolved));
        }

        if (!empty($issues)) {
            $this->warn('Обнаружены проблемы: ' . implode('; ', $issues));
        } else {
            $this->info('Проблемы устранены');
        }

        return Command::SUCCESS;
    }
}
