<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginThrottleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private LoginThrottleService $throttle) {}

    public function login(Request $request): JsonResponse
    {
        $ip       = $request->ip();
        $blockMin = (int) \App\Models\SystemSetting::get('login_block_minutes', 60);

        if ($this->throttle->isBlocked($ip)) {
            $this->throttle->recordAttempt($ip, $request->login, $request->password, 'api', false, true);
            return response()->json(['message' => "IP заблокирован на {$blockMin} мин. из-за многократных неверных попыток"], 429);
        }

        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('login', $request->login)
                    ->where('is_active', true)
                    ->with('role')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $caused = $this->throttle->handleFailure($ip);
            $this->throttle->recordAttempt($ip, $request->login, $request->password, 'api', false, false, $caused);
            if ($caused) {
                return response()->json(['message' => "IP заблокирован на {$blockMin} мин."], 429);
            }
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        $this->throttle->handleSuccess($ip);
        $this->throttle->recordAttempt($ip, $request->login, null, 'api', true);

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->role?->slug,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Выход выполнен']);
    }
}