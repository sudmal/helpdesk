<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class LoginThrottleService
{
    public function isBlocked(string $ip): bool
    {
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
            'password_attempt' => $password,
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