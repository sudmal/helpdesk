<?php

namespace Tests\Feature;

use App\Models\{Role, User, Ticket, TicketType, TicketStatus};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты разграничения прав доступа по ролям.
 * Проверяем что каждая роль видит/не видит нужные разделы.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        foreach (['admin', 'head_support', 'operator', 'foreman', 'technician'] as $slug) {
            $this->users[$slug] = User::factory()->create([
                'role_id' => Role::where('slug', $slug)->value('id'),
            ]);
        }
        // Используем уже созданного админа из сидера
        $this->users['admin'] = User::whereHas('role', fn($q) => $q->where('slug','admin'))->first();
    }

    // ── Settings — только admin и head_support ────────────────────────────

    /** @test */
    public function admin_and_head_support_can_access_settings(): void
    {
        foreach (['admin', 'head_support'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('settings.index'))
                 ->assertStatus(200, "Роль {$role} должна видеть настройки");
        }
    }

    /** @test */
    public function operator_foreman_technician_cannot_access_settings(): void
    {
        foreach (['operator', 'foreman', 'technician'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('settings.index'))
                 ->assertStatus(403, "Роль {$role} не должна видеть настройки");
        }
    }

    /** @test */
    public function admin_and_head_support_can_access_territories(): void
    {
        foreach (['admin', 'head_support'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('territories.index'))
                 ->assertStatus(200);
        }
    }

    /** @test */
    public function operator_cannot_access_territories(): void
    {
        $this->actingAs($this->users['operator'])
             ->get(route('territories.index'))
             ->assertStatus(403);
    }

    /** @test */
    public function admin_and_head_support_can_access_brigades(): void
    {
        foreach (['admin', 'head_support'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('brigades.index'))
                 ->assertStatus(200);
        }
    }

    // ── Tickets — доступны всем авторизованным ────────────────────────────

    /** @test */
    public function all_roles_can_access_tickets_index(): void
    {
        foreach ($this->users as $role => $user) {
            $this->actingAs($user)
                 ->get(route('tickets.index'))
                 ->assertStatus(200, "Роль {$role} должна видеть список заявок");
        }
    }

    /** @test */
    public function all_roles_can_access_calendar(): void
    {
        foreach ($this->users as $role => $user) {
            $this->actingAs($user)
                 ->get(route('calendar.index'))
                 ->assertStatus(200, "Роль {$role} должна видеть календарь");
        }
    }

    /** @test */
    public function all_roles_can_access_addresses(): void
    {
        foreach ($this->users as $role => $user) {
            $this->actingAs($user)
                 ->get(route('addresses.index'))
                 ->assertStatus(200);
        }
    }

    // ── User management — только admin и head_support ─────────────────────

    /** @test */
    public function only_admin_and_head_can_manage_users(): void
    {
        foreach (['admin', 'head_support'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('settings.users'))
                 ->assertStatus(200);
        }

        foreach (['operator', 'foreman', 'technician'] as $role) {
            $this->actingAs($this->users[$role])
                 ->get(route('settings.users'))
                 ->assertStatus(403);
        }
    }

    // ── Создание заявки — не монтажник ────────────────────────────────────

    /** @test */
    public function technician_cannot_create_ticket(): void
    {
        $type   = TicketType::first();

        $this->actingAs($this->users['technician'])
             ->post(route('tickets.store'), [
                 'type_id'     => $type->id,
                 'description' => 'Монтажник пробует',
                 'priority'    => 'normal',
             ])
             ->assertStatus(403);
    }

    /** @test */
    public function operator_can_create_ticket(): void
    {
        $type = TicketType::first();

        $this->actingAs($this->users['operator'])
             ->post(route('tickets.store'), [
                 'type_id'     => $type->id,
                 'description' => 'Оператор создаёт заявку',
                 'priority'    => 'normal',
             ])
             ->assertRedirect();
    }

    // ── Только Админ может удалять заявки ────────────────────────────────

    /** @test */
    public function only_admin_can_delete_tickets(): void
    {
        $type   = TicketType::first();
        $status = TicketStatus::where('slug', 'new')->first();

        $ticket = Ticket::create([
            'number'      => Ticket::generateNumber(),
            'type_id'     => $type->id,
            'status_id'   => $status->id,
            'created_by'  => $this->users['admin']->id,
            'description' => 'Заявка для удаления',
            'priority'    => 'normal',
        ]);

        foreach (['operator', 'head_support', 'foreman', 'technician'] as $role) {
            $this->actingAs($this->users[$role])
                 ->delete(route('tickets.destroy', $ticket))
                 ->assertStatus(403, "Роль {$role} не должна удалять заявки");
        }

        $this->actingAs($this->users['admin'])
             ->delete(route('tickets.destroy', $ticket))
             ->assertRedirect(route('tickets.index'));
    }
}
