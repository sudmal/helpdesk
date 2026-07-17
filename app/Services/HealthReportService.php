<?php

namespace App\Services;

class HealthReportService
{
    private const BACKUPS_DIR = '/var/backups/helpdesk';

    public function collect(): array
    {
        return [
            'disk'     => $this->disk(),
            'smart'    => $this->smart(),
            'cpu'      => $this->cpu(),
            'memory'   => $this->memory(),
            'uptime'   => $this->uptime(),
            'services' => $this->services(),
            'backups'  => $this->backups(),
        ];
    }

    private function backups(): array
    {
        if (!is_dir(self::BACKUPS_DIR)) {
            return [];
        }

        return collect(scandir(self::BACKUPS_DIR))
            ->reject(fn ($f) => in_array($f, ['.', '..']))
            ->map(fn ($f) => [
                'name'  => $f,
                'size'  => filesize(self::BACKUPS_DIR . '/' . $f),
                'mtime' => filemtime(self::BACKUPS_DIR . '/' . $f),
            ])
            ->sortByDesc('mtime')
            ->values()
            ->all();
    }

    private function disk(): array
    {
        $total = disk_total_space('/');
        $free  = disk_free_space('/');
        $used  = $total - $free;

        return [
            'total_bytes' => $total,
            'used_bytes'  => $used,
            'free_bytes'  => $free,
            'used_pct'    => $total > 0 ? round($used / $total * 100, 1) : null,
        ];
    }

    // SMART требует прямого доступа к /dev/sda, который PHP-FPM намеренно не
    // имеет (PrivateDevices в systemd-юните — не ослабляем ради этого таба).
    // Вместо этого читаем снимок, который каждые 10 минут пишет root через
    // smart-snapshot.timer — см. /usr/local/bin/smart-snapshot.sh.
    private const SNAPSHOT_PATH = '/var/cache/vega8-smart.json';

    private function smart(): ?array
    {
        if (!is_file(self::SNAPSHOT_PATH)) {
            return null;
        }

        $raw = @file_get_contents(self::SNAPSHOT_PATH);
        if (!$raw) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['smart_status'])) {
            \Log::warning('HealthReportService: не удалось разобрать снимок smartctl', ['raw' => $raw]);
            return null;
        }

        $attrs = collect($data['ata_smart_attributes']['table'] ?? []);
        $find  = fn (int $id) => $attrs->firstWhere('id', $id);

        $lifetimeRemaining = $find(202);
        $reallocated       = $find(5);

        return [
            'passed'              => (bool) ($data['smart_status']['passed'] ?? false),
            'lifetime_remain_pct' => $lifetimeRemaining['value'] ?? null,
            'reallocated_blocks'  => $reallocated['raw']['value'] ?? null,
            'power_on_hours'      => $data['power_on_time']['hours'] ?? null,
            'temperature_c'       => $data['temperature']['current'] ?? null,
            'snapshot_at'         => filemtime(self::SNAPSHOT_PATH),
        ];
    }

    private function cpu(): array
    {
        $load = sys_getloadavg();

        return [
            'load1'  => $load[0] ?? null,
            'load5'  => $load[1] ?? null,
            'load15' => $load[2] ?? null,
            'cores'  => (int) (shell_exec('nproc') ?: 0),
        ];
    }

    private function memory(): array
    {
        $meminfo = @file_get_contents('/proc/meminfo');
        if (!$meminfo) {
            return [];
        }

        preg_match_all('/^(\w+):\s+(\d+)\s+kB$/m', $meminfo, $matches, PREG_SET_ORDER);
        $kb = [];
        foreach ($matches as $m) {
            $kb[$m[1]] = (int) $m[2];
        }

        $totalKb     = $kb['MemTotal'] ?? 0;
        $availableKb = $kb['MemAvailable'] ?? 0;
        $swapTotalKb = $kb['SwapTotal'] ?? 0;
        $swapFreeKb  = $kb['SwapFree'] ?? 0;

        return [
            'total_bytes'      => $totalKb * 1024,
            'available_bytes'  => $availableKb * 1024,
            'used_bytes'       => ($totalKb - $availableKb) * 1024,
            'used_pct'         => $totalKb > 0 ? round(($totalKb - $availableKb) / $totalKb * 100, 1) : null,
            'swap_total_bytes' => $swapTotalKb * 1024,
            'swap_used_bytes'  => ($swapTotalKb - $swapFreeKb) * 1024,
        ];
    }

    private function uptime(): ?float
    {
        $raw = @file_get_contents('/proc/uptime');
        if (!$raw) {
            return null;
        }

        return (float) explode(' ', trim($raw))[0];
    }

    /**
     * Список проблем по отчёту collect() — общая логика для алертов и для
     * подсветки проблемных значений на вкладке в Настройках.
     * @return string[] человекочитаемые описания найденных проблем
     */
    public function evaluateAnomalies(array $report): array
    {
        $issues = [];

        if (($report['disk']['used_pct'] ?? 0) >= 90) {
            $issues[] = "Диск заполнен на {$report['disk']['used_pct']}%";
        }

        // Бэкап — раз в сутки в 03:00, порог с запасом на случай задержки
        $lastBackup = collect($report['backups'])->max('mtime');
        if ($lastBackup === null) {
            $issues[] = 'Бэкапы не найдены';
        } elseif (now()->timestamp - $lastBackup > 30 * 3600) {
            $hoursAgo = round((now()->timestamp - $lastBackup) / 3600);
            $issues[] = "Последний бэкап был {$hoursAgo} ч. назад";
        }

        $smart = $report['smart'];
        if ($smart !== null) {
            if ($smart['passed'] === false) {
                $issues[] = 'SMART: диск сообщает об ошибке (health check failed)';
            }
            if (($smart['reallocated_blocks'] ?? 0) > 0) {
                $issues[] = "SMART: обнаружены reallocated-блоки ({$smart['reallocated_blocks']})";
            }
            if ($smart['lifetime_remain_pct'] !== null && $smart['lifetime_remain_pct'] < 10) {
                $issues[] = "SMART: ресурс SSD на исходе — осталось {$smart['lifetime_remain_pct']}%";
            }
        }

        foreach ($report['services'] as $service) {
            if (!$service['active']) {
                $issues[] = "Сервис не активен: {$service['name']}";
            }
        }

        if (($report['memory']['used_pct'] ?? 0) >= 95) {
            $issues[] = "Память заполнена на {$report['memory']['used_pct']}%";
        }

        $cores = $report['cpu']['cores'] ?: 1;
        if (($report['cpu']['load1'] ?? 0) > $cores * 4) {
            $issues[] = "Высокая нагрузка CPU: load1={$report['cpu']['load1']} при {$cores} ядрах";
        }

        return $issues;
    }

    private function services(): array
    {
        $names = ['nginx', 'php8.2-fpm', 'mariadb', 'redis-server', 'supervisor', 'fail2ban', 'tg-tunnel'];
        $cmd   = 'systemctl is-active ' . implode(' ', $names) . ' 2>&1';
        $out   = trim((string) shell_exec($cmd));
        $lines = $out !== '' ? explode("\n", $out) : [];

        $result = [];
        foreach ($names as $i => $name) {
            $result[] = [
                'name'   => $name,
                'active' => ($lines[$i] ?? '') === 'active',
            ];
        }

        return $result;
    }
}
