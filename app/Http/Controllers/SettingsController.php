<?php

namespace App\Http\Controllers;

use App\Models\{TicketType, TicketStatus, User, Role, Territory, ServiceType, SystemSetting};
use App\Console\Commands\{SendDailySummary, SendEveningReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Artisan};
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $this->authorize('manage-settings');

        return Inertia::render('Settings/Index', [
            'ticketTypes'      => TicketType::orderBy('sort_order')->get(),
            'ticketStatuses'   => TicketStatus::orderBy('sort_order')->get(),
            'serviceTypes'     => ServiceType::orderBy('sort_order')->get(),
            'users'            => User::with(['role', 'territories'])->orderBy('name')->get(),
            'roles'            => Role::orderBy('name')->get(),
            'territories'      => Territory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'lanbillingEnabled' => (bool) SystemSetting::get('lanbilling_enabled', true),
            'lanbillingConfig' => [
                'url'   => config('lanbilling.url'),
                'login' => config('lanbilling.login'),
            ],
            'generalSettings'  => [
                'work_hours_start'      => SystemSetting::get('work_hours_start', '09:00'),
                'work_hours_end'        => SystemSetting::get('work_hours_end', '17:00'),
                'schedule_step_minutes' => SystemSetting::get('schedule_step_minutes', 30),
                'attachment_ttl_days'   => SystemSetting::get('attachment_ttl_days', 365),
                'work_days'             => SystemSetting::get('work_days', '1,2,3,4,5'),
            ],
        ]);
    }

    // ── Типы заявок ──────────────────────────────────────────────────

    public function storeType(Request $request)
    {
        $this->authorize('manage-settings');
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
        $this->authorize('manage-settings');
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
        $this->authorize('manage-settings');
        if ($ticketType->tickets()->exists()) {
            return back()->withErrors(['type' => 'Нельзя удалить — есть связанные заявки']);
        }
        $ticketType->delete();
        return back()->with('success', 'Тип удалён');
    }

    // ── Статусы ──────────────────────────────────────────────────────

    public function storeStatus(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'slug'             => 'required|string|max:50|unique:ticket_statuses,slug|alpha_dash',
            'color'            => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_final'         => 'boolean',
            'requires_comment' => 'boolean',
            'sort_order'       => 'nullable|integer',
        ]);
        TicketStatus::create($data);
        return back()->with('success', 'Статус добавлен');
    }

    public function updateStatus(Request $request, TicketStatus $ticketStatus)
    {
        $this->authorize('manage-settings');
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
        $this->authorize('manage-settings');
        if ($ticketStatus->tickets()->exists()) {
            return back()->withErrors(['status' => 'Нельзя удалить — есть связанные заявки']);
        }
        $ticketStatus->delete();
        return back()->with('success', 'Статус удалён');
    }

    // ── Пользователи ─────────────────────────────────────────────────

    public function storeUser(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'login'            => 'required|string|max:50|unique:users,login|alpha_dash',
            'email'            => 'nullable|email|unique:users,email|sometimes',
            'phone'            => 'nullable|string|max:20',
            'password'         => 'required|string|min:8|confirmed',
            'role_id'          => 'required|exists:roles,id',
            'telegram_chat_id' => 'nullable|string',
            'notify_telegram'  => 'boolean',
            'notify_email'     => 'boolean',
            'territory_ids'    => 'nullable|array',
            'territory_ids.*'  => 'exists:territories,id',
        ]);
        $data['password'] = Hash::make($data['password']);
        $territoryIds = $data['territory_ids'] ?? [];
        unset($data['territory_ids']);

        $user = User::create($data);
        if ($territoryIds) {
            $user->territories()->sync($territoryIds);
        }
        return back()->with('success', 'Пользователь создан');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'             => 'required|string|max:200',
            'login'            => 'required|string|max:50|alpha_dash|unique:users,login,' . $user->id,
            'email'            => 'nullable|email|unique:users,email,' . $user->id,
            'phone'            => 'nullable|string|max:20',
            'role_id'          => 'required|exists:roles,id',
            'is_active'        => 'boolean',
            'telegram_chat_id' => 'nullable|string',
            'notify_telegram'  => 'boolean',
            'notify_email'     => 'boolean',
            'territory_ids'    => 'nullable|array',
            'territory_ids.*'  => 'exists:territories,id',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = Hash::make($request->password);
        }

        $territoryIds = $data['territory_ids'] ?? [];
        unset($data['territory_ids']);

        $user->update($data);
        $user->territories()->sync($territoryIds);

        return back()->with('success', 'Пользователь обновлён');
    }

    public function destroyUser(User $user)
    {
        $this->authorize('manage-settings');
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Нельзя удалить себя']);
        }
        $user->territories()->detach();
        $user->delete();
        return back()->with('success', 'Пользователь удалён');
    }

    // ── Роли ─────────────────────────────────────────────────────────

    public function updateRole(Request $request, Role $role)
    {
        $this->authorize('manage-settings');

        // Нельзя менять права роли admin через UI
        if ($role->slug === 'admin') {
            return back()->withErrors(['role' => 'Права роли Администратор нельзя изменить']);
        }

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'permissions'   => 'required|array',
            'permissions.*' => 'string',
        ]);

        $role->update($data);
        return back()->with('success', 'Роль обновлена');
    }

    // ── Уведомления (ручной запуск) ───────────────────────────────────

    public function sendDailySummary()
    {
        $this->authorize('manage-settings');
        Artisan::call('helpdesk:daily-summary');
        return response()->json(['ok' => true, 'message' => 'Утренняя сводка отправлена']);
    }

    public function sendEveningReport()
    {
        $this->authorize('manage-settings');
        Artisan::call('helpdesk:evening-report');
        return response()->json(['ok' => true, 'message' => 'Вечерний отчёт отправлен']);
    }

    // ── Общие настройки ─────────────────────────────────────────────────

    public function sortServiceTypes(\Illuminate\Http\Request $request)
    {
        $this->authorize('manage-settings');
        foreach ($request->order as $index => $id) {
            \App\Models\ServiceType::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['ok' => true]);
    }

    public function sortTerritories(\Illuminate\Http\Request $request)
    {
        $this->authorize('manage-settings');
        foreach ($request->order as $index => $id) {
            \App\Models\Territory::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['ok' => true]);
    }

    public function updateGeneral(\Illuminate\Http\Request $request)
    {
        $this->authorize('manage-settings');

        // Если передан только переключатель — сохраняем и выходим
        if ($request->has('lanbilling_enabled') && !$request->has('work_hours_start')) {
            SystemSetting::set('lanbilling_enabled', $request->boolean('lanbilling_enabled'));
            return back()->with('success', 'Настройки сохранены');
        }

        $data = $request->validate([
            'work_hours_start'      => 'required|date_format:H:i',
            'work_hours_end'        => 'required|date_format:H:i',
            'schedule_step_minutes' => 'required|integer|in:15,30,60',
            'attachment_ttl_days'   => 'required|integer|min:0',
            'work_days'             => 'required|array',
            'work_days.*'           => 'in:1,2,3,4,5,6,7',
        ]);

        SystemSetting::set('lanbilling_enabled', $request->boolean('lanbilling_enabled'));
        SystemSetting::set('work_hours_start',      $data['work_hours_start']);
        SystemSetting::set('work_hours_end',        $data['work_hours_end']);
        SystemSetting::set('schedule_step_minutes', $data['schedule_step_minutes']);
        SystemSetting::set('attachment_ttl_days',   $data['attachment_ttl_days']);
        SystemSetting::set('work_days',             implode(',', $data['work_days']));

        return back()->with('success', 'Настройки сохранены');
    }

    // ── LANBilling ───────────────────────────────────────────────────

    public function lanbilling()
    {
        $this->authorize('manage-settings');
        return Inertia::render('Settings/Index', [
            'config' => [
                'url'   => config('lanbilling.url'),
                'login' => config('lanbilling.login'),
            ],
        ]);
    }

    public function updateLanbilling(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'url'      => 'required|url',
            'login'    => 'required|string',
            'password' => 'nullable|string',
        ]);

        $this->setEnv('LANBILLING_URL',   $data['url']);
        $this->setEnv('LANBILLING_LOGIN', $data['login']);
        if (!empty($data['password'])) {
            $this->setEnv('LANBILLING_PASSWORD', $data['password']);
        }

        return back()->with('success', 'Настройки LANBilling сохранены');
    }

    private function setEnv(string $key, string $value): void
    {
        $path    = base_path('.env');
        $content = file_get_contents($path);
        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }
        file_put_contents($path, $content);
    }
}
