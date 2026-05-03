<?php

namespace App\Http\Controllers;

use App\Models\{TicketType, TicketStatus, User, Role};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'ticketTypes'    => TicketType::orderBy('sort_order')->get(),
            'ticketStatuses' => TicketStatus::orderBy('sort_order')->get(),
        ]);
    }

    // === Типы заявок ===

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:ticket_types,name',
            'color'      => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'sort_order' => 'nullable|integer',
        ]);
        TicketType::create($data);
        return back()->with('success', 'Тип добавлен');
    }

    public function updateType(Request $request, TicketType $ticketType)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:ticket_types,name,' . $ticketType->id,
            'color'      => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $ticketType->update($data);
        return back()->with('success', 'Тип обновлён');
    }

    public function destroyType(TicketType $ticketType)
    {
        if ($ticketType->tickets()->exists()) {
            return back()->withErrors(['type' => 'Нельзя удалить тип — есть связанные заявки']);
        }
        $ticketType->delete();
        return back()->with('success', 'Тип удалён');
    }

    // === Статусы заявок ===

    public function storeStatus(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'slug'              => 'required|string|max:50|unique:ticket_statuses,slug|alpha_dash',
            'color'             => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_final'          => 'boolean',
            'requires_comment'  => 'boolean',
            'sort_order'        => 'nullable|integer',
        ]);
        TicketStatus::create($data);
        return back()->with('success', 'Статус добавлен');
    }

    public function updateStatus(Request $request, TicketStatus $ticketStatus)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'slug'             => 'required|string|max:50|alpha_dash|unique:ticket_statuses,slug,' . $ticketStatus->id,
            'color'            => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_final'         => 'boolean',
            'requires_comment' => 'boolean',
            'is_active'        => 'boolean',
            'sort_order'       => 'nullable|integer',
        ]);
        $ticketStatus->update($data);
        return back()->with('success', 'Статус обновлён');
    }

    public function destroyStatus(TicketStatus $ticketStatus)
    {
        if ($ticketStatus->tickets()->exists()) {
            return back()->withErrors(['status' => 'Нельзя удалить статус — есть связанные заявки']);
        }
        $ticketStatus->delete();
        return back()->with('success', 'Статус удалён');
    }

    // === Пользователи ===

    public function users()
    {
        return Inertia::render('Settings/Users', [
            'users' => User::with('role')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'email'             => 'required|email|unique:users,email',
            'phone'             => 'nullable|string|max:20',
            'password'          => 'required|string|min:8|confirmed',
            'role_id'           => 'required|exists:roles,id',
            'telegram_chat_id'  => 'nullable|string',
            'notify_telegram'   => 'boolean',
            'notify_email'      => 'boolean',
        ]);
        $data['password'] = Hash::make($data['password']);
        User::create($data);
        return back()->with('success', 'Пользователь создан');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'email'            => 'required|email|unique:users,email,' . $user->id,
            'phone'            => 'nullable|string|max:20',
            'role_id'          => 'required|exists:roles,id',
            'is_active'        => 'boolean',
            'telegram_chat_id' => 'nullable|string',
            'notify_telegram'  => 'boolean',
            'notify_email'     => 'boolean',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        return back()->with('success', 'Пользователь обновлён');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Нельзя удалить себя']);
        }
        $user->update(['is_active' => false]);
        return back()->with('success', 'Пользователь деактивирован');
    }

    // === LANBilling ===

    public function lanbilling()
    {
        return Inertia::render('Settings/LanBilling', [
            'config' => [
                'url'      => config('lanbilling.url'),
                'login'    => config('lanbilling.login'),
            ],
        ]);
    }

    public function updateLanbilling(Request $request)
    {
        $data = $request->validate([
            'url'      => 'required|url',
            'login'    => 'required|string',
            'password' => 'nullable|string',
        ]);

        // Пишем в .env (в продакшене лучше хранить в БД/config table)
        $this->setEnv('LANBILLING_URL',      $data['url']);
        $this->setEnv('LANBILLING_LOGIN',    $data['login']);
        if (!empty($data['password'])) {
            $this->setEnv('LANBILLING_PASSWORD', $data['password']);
        }

        return back()->with('success', 'Настройки LANBilling сохранены');
    }

    private function setEnv(string $key, string $value): void
    {
        $path    = base_path('.env');
        $content = file_get_contents($path);
        $escaped = preg_quote("={$value}", '/');

        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($path, $content);
    }
}
