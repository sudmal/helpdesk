<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class LoginThrottleService
{
    // Доверенные сети, которые никогда не блокируются логин-троттлингом --
    // офисный NAT-выход, доверенный внешний диапазон и все приватные сети
    // (RFC1918: в т.ч. VPN/внутренние сервисы, которые ходят с серых IP).
    // Тот же набор доверенных сетей, что уже освобождён от rate-limit на
    // уровне nginx (см. память project_server_migration), здесь отдельно,
    // потому что это другой, независимый механизм (приложение, не веб-сервер).
    // Настраивается через SystemSetting 'login_ip_whitelist' (CIDR через
    // запятую/перевод строки, редактируется в Настройках -> Безопасность) --
    // пока запись в БД не создана (или значение пустое), действует этот
    // список по умолчанию.
    private const DEFAULT_WHITELIST = [
        '193.233.140.0/22',
        '212.66.43.0/24',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /** Текущий белый список как строка (для отображения/редактирования в UI) */
    public function getWhitelistRaw(): string
    {
        $raw = SystemSetting::get('login_ip_whitelist');
        return $raw !== null && trim($raw) !== '' ? $raw : implode("\n", self::DEFAULT_WHITELIST);
    }

    /** @return string[] разобранные CIDR из текущего белого списка */
    public function getWhitelistCidrs(): array
    {
        return preg_split('/[\s,]+/', trim($this->getWhitelistRaw()), -1, PREG_SPLIT_NO_EMPTY);
    }

    public function isWhitelisted(string $ip): bool
    {
        foreach ($this->getWhitelistCidrs() as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) return true;
        }
        return false;
    }

    /** Валидный ли CIDR/IP (для проверки перед сохранением из UI) */
    public function isValidCidr(string $cidr): bool
    {
        if (!str_contains($cidr, '/')) $cidr .= '/32';
        [$subnet, $bits] = array_pad(explode('/', $cidr), 2, null);
        if (ip2long($subnet ?? '') === false) return false;
        return $bits !== null && ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= 32;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) $cidr .= '/32';
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;
        $bits = (int) $bits;
        if ($bits <= 0) return true;
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    public function isBlocked(string $ip): bool
    {
        if ($this->isWhitelisted($ip)) return false;

        $blockT = (int) SystemSetting::get('login_block_attempts', 6);

        if ((int) Cache::get("login_fails:{$ip}", 0) >= $blockT) {
            return true;
        }

        return BlockedIp::where('ip', $ip)
            ->whereNull('unblocked_at')
            ->where(fn($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()))
            ->exists();
    }

    public function handleFailure(string $ip): bool
    {
        if ($this->isWhitelisted($ip)) return false;

        $blockT   = (int) SystemSetting::get('login_block_attempts', 6);
        $blockMin = (int) SystemSetting::get('login_block_minutes', 60);
        $key      = "login_fails:{$ip}";

        Cache::add($key, 0, now()->addMinutes($blockMin));
        $count = (int) Cache::increment($key);

        if ($count >= $blockT) {
            BlockedIp::where('ip', $ip)->whereNull('unblocked_at')->delete();
            BlockedIp::create([
                'ip'           => $ip,
                'auto_blocked' => true,
                'blocked_until' => now()->addMinutes($blockMin),
            ]);
            return true;
        }

        return false;
    }

    public function handleSuccess(string $ip): void
    {
        Cache::forget("login_fails:{$ip}");
    }

    public function unblock(string $ip): void
    {
        Cache::forget("login_fails:{$ip}");
        BlockedIp::where('ip', $ip)->whereNull('unblocked_at')
            ->update(['unblocked_at' => now()]);
    }

    public function recordAttempt(
        string $ip,
        ?string $login,
        ?string $password,
        string $method,
        bool $success,
        bool $wasBlocked = false,
        bool $causedBlock = false
    ): void {
        LoginAttempt::create([
            'ip'               => $ip,
            'login'            => $login,
            'password_attempt' => null,
            'method'           => $method,
            'success'          => $success,
            'was_blocked'      => $wasBlocked,
            'caused_block'     => $causedBlock,
        ]);
    }

    public function getBlockedIps()
    {
        return BlockedIp::whereNull('unblocked_at')
            ->where(fn($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()))
            ->latest()
            ->get();
    }

    public function getAttempts(int $limit = 200)
    {
        return LoginAttempt::latest()->limit($limit)->get();
    }

    public function getAttemptCount(string $ip): int
    {
        return (int) Cache::get("login_fails:{$ip}", 0);
    }
}