<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // === РОЛИ ===
        $roles = [
            [
                'name' => 'Администратор',
                'slug' => 'admin',
                'permissions' => ['*'],  // все права
            ],
            [
                'name' => 'Начальник ТП',
                'slug' => 'head_support',
                'permissions' => [
                    'tickets.*', 'users.operators.*', 'settings.*',
                    'reports.*', 'territories.view', 'brigades.view',
                ],
            ],
            [
                'name' => 'Оператор ТП',
                'slug' => 'operator',
                'permissions' => [
                    'tickets.*', 'addresses.*', 'calendar.view',
                ],
            ],
            [
                'name' => 'Бригадир',
                'slug' => 'foreman',
                'permissions' => [
                    'tickets.view', 'tickets.update', 'tickets.assign',
                    'tickets.close', 'tickets.comment', 'calendar.view',
                ],
            ],
            [
                'name' => 'Монтажник',
                'slug' => 'technician',
                'permissions' => [
                    'tickets.view', 'tickets.start', 'tickets.pause',
                    'tickets.close', 'tickets.comment',
                ],
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // === ADMIN USER ===
        $adminRole = Role::where('slug', 'admin')->first();
        User::firstOrCreate(
            ['email' => 'admin@helpdesk.local'],
            [
                'name'     => 'Администратор',
                'password' => Hash::make('Admin1234!'),
                'role_id'  => $adminRole->id,
                'is_active' => true,
            ]
        );

        // === ТИПЫ ЗАЯВОК ===
        $types = [
            ['name' => 'Подключение',        'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'Ремонт',             'color' => '#ef4444', 'sort_order' => 2],
            ['name' => 'Восстановление',     'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => 'Настройка оборудования', 'color' => '#8b5cf6', 'sort_order' => 4],
            ['name' => 'Замена оборудования',    'color' => '#10b981', 'sort_order' => 5],
        ];
        foreach ($types as $type) {
            TicketType::firstOrCreate(['name' => $type['name']], $type);
        }

        // === СТАТУСЫ ЗАЯВОК ===
        $statuses = [
            ['name' => 'Новая',       'slug' => 'new',         'color' => '#3b82f6', 'is_final' => false, 'sort_order' => 1],
            ['name' => 'В работе',    'slug' => 'in_progress', 'color' => '#f59e0b', 'is_final' => false, 'sort_order' => 2],
            ['name' => 'Приостановлена', 'slug' => 'paused',   'color' => '#6b7280', 'is_final' => false, 'sort_order' => 3],
            ['name' => 'Перенесена',  'slug' => 'postponed',   'color' => '#8b5cf6', 'is_final' => false, 'sort_order' => 4],
            ['name' => 'Закрыта',     'slug' => 'closed',      'color' => '#10b981', 'is_final' => true,  'sort_order' => 5],
            ['name' => 'Отменена',    'slug' => 'cancelled',   'color' => '#ef4444', 'is_final' => true,  'sort_order' => 6],
        ];
        foreach ($statuses as $status) {
            TicketStatus::firstOrCreate(['slug' => $status['slug']], $status);
        }
    }
}
