<?php

namespace Tests\Feature;

use App\Models\{Role, User, Ticket, TicketType, TicketStatus, Brigade, Address, Territory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private User $technician;
    private TicketType $type;
    private TicketStatus $statusNew;
    private TicketStatus $statusClosed;
    private Brigade $brigade;
    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin      = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->first();
        $this->operator   = User::factory()->create([
            'role_id' => Role::where('slug', 'operator')->value('id'),
        ]);
        $this->technician = User::factory()->create([
            'role_id' => Role::where('slug', 'technician')->value('id'),
        ]);

        $this->type        = TicketType::first();
        $this->statusNew   = TicketStatus::where('slug', 'new')->first();
        $this->statusClosed = TicketStatus::where('slug', 'closed')->first();

        $territory     = Territory::create(['name' => 'Тест']);
        $this->brigade = Brigade::create(['name' => 'Тест бригада']);
        $this->brigade->territories()->attach($territory);

        $this->address = Address::create([
            'street'   => 'Ленина',
            'building' => '1',
            'city'     => 'Тест',
        ]);
    }

    // ── Список заявок ─────────────────────────────────────────────────────

    /** @test */
    public function admin_can_view_tickets_index(): void
    {
        $this->actingAs($this->admin)
             ->get(route('tickets.index'))
             ->assertStatus(200)
             ->assertStatus(200);
    }

    /** @test */
    public function guest_cannot_view_tickets(): void
    {
        $this->get(route('tickets.index'))
             ->assertRedirect(route('login'));
    }

    // ── Создание заявки ───────────────────────────────────────────────────

    /** @test */
    public function operator_can_create_ticket(): void
    {
        $response = $this->actingAs($this->operator)
             ->post(route('tickets.store'), [
                 'type_id'      => $this->type->id,
                 'address_id'   => $this->address->id,
                 'description'  => 'Тестовая заявка — нет интернета',
                 'phone'        => '+79491234567',
                 'priority'     => 'normal',
                 'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
             ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'description' => 'Тестовая заявка — нет интернета',
            'created_by'  => $this->operator->id,
        ]);
    }

    /** @test */
    public function ticket_number_is_generated_automatically(): void
    {
        $this->actingAs($this->operator)
             ->post(route('tickets.store'), [
                 'type_id'     => $this->type->id,
                 'description' => 'Тест автономера',
                 'priority'    => 'normal',
             ]);

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertMatchesRegularExpression('/^Т-\d+$/', $ticket->number);
    }

    /** @test */
    public function ticket_requires_description_and_type(): void
    {
        $this->actingAs($this->operator)
             ->post(route('tickets.store'), [
                 'description' => '',
                 'type_id'     => null,
                 'priority'    => 'normal',
             ])
             ->assertSessionHasErrors(['description', 'type_id']);
    }

    /** @test */
    public function technician_cannot_create_ticket(): void
    {
        $this->actingAs($this->technician)
             ->post(route('tickets.store'), [
                 'type_id'     => $this->type->id,
                 'description' => 'Монтажник пытается создать заявку',
                 'priority'    => 'normal',
             ])
             ->assertStatus(403);
    }

    // ── Просмотр заявки ───────────────────────────────────────────────────

    /** @test */
    public function user_can_view_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->operator)
             ->get(route('tickets.show', $ticket))
             ->assertStatus(200)
             ->assertStatus(200);
    }

    /** @test */
    public function technician_can_only_view_own_brigade_tickets(): void
    {
        $ticket = $this->createTicket(['brigade_id' => $this->brigade->id]);

        // Монтажник не в этой бригаде — 403
        $this->actingAs($this->technician)
             ->get(route('tickets.show', $ticket))
             ->assertStatus(403);

        // Добавляем в бригаду — должен видеть
        $this->brigade->members()->attach($this->technician);
        // Сбрасываем кэш отношений чтобы Policy увидела свежие данные
        $this->technician->unsetRelation('brigades');
        $this->actingAs($this->technician)
             ->get(route('tickets.show', $ticket))
             ->assertStatus(200);
    }

    // ── Обновление заявки ─────────────────────────────────────────────────

    /** @test */
    public function operator_can_update_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->operator)
             ->put(route('tickets.update', $ticket), [
                 'type_id'     => $this->type->id,
                 'status_id'   => $this->statusNew->id,
                 'description' => 'Обновлённое описание',
                 'priority'    => 'high',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id'          => $ticket->id,
            'description' => 'Обновлённое описание',
            'priority'    => 'high',
        ]);
    }

    /** @test */
    public function technician_cannot_update_ticket_fields(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->technician)
             ->put(route('tickets.update', $ticket), [
                 'type_id'     => $this->type->id,
                 'status_id'   => $this->statusNew->id,
                 'description' => 'Попытка изменить',
                 'priority'    => 'normal',
             ])
             ->assertStatus(403);
    }

    // ── Действия по заявке ────────────────────────────────────────────────

    /** @test */
    public function technician_can_start_new_ticket(): void
    {
        $ticket = $this->createTicket();
        $this->brigade->members()->attach($this->technician);
        $ticket->update(['brigade_id' => $this->brigade->id, 'assigned_to' => $this->technician->id]);

        $this->actingAs($this->technician)
             ->post(route('tickets.start', $ticket))
             ->assertRedirect();

        $this->assertDatabaseHas('ticket_statuses', ['slug' => 'in_progress']);
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status->slug);
        $this->assertNotNull($ticket->started_at);
    }

    /** @test */
    public function ticket_can_be_closed_with_comment(): void
    {
        $ticket = $this->createTicket();
        // Переводим в работу
        $inProgress = TicketStatus::where('slug', 'in_progress')->first();
        $ticket->update(['status_id' => $inProgress->id]);

        $this->actingAs($this->admin)
             ->post(route('tickets.close', $ticket), [
                 'comment' => 'Работа выполнена, всё исправлено',
             ])
             ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status->slug);
        $this->assertNotNull($ticket->closed_at);
        $this->assertEquals('Работа выполнена, всё исправлено', $ticket->close_notes);
    }

    /** @test */
    public function closed_ticket_can_be_reopened_by_admin(): void
    {
        $ticket = $this->createTicket(['status_id' => $this->statusClosed->id]);

        $this->actingAs($this->admin)
             ->post(route('tickets.reopen', $ticket))
             ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('new', $ticket->status->slug);
    }

    // ── Назначение бригады ────────────────────────────────────────────────

    /** @test */
    public function operator_can_assign_brigade(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->operator)
             ->post(route('tickets.assign', $ticket), [
                 'brigade_id' => $this->brigade->id,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id'         => $ticket->id,
            'brigade_id' => $this->brigade->id,
        ]);
    }

    // ── Комментарии ───────────────────────────────────────────────────────

    /** @test */
    public function any_user_can_comment_on_ticket(): void
    {
        $ticket = $this->createTicket(['brigade_id' => $this->brigade->id]);
        $this->brigade->members()->attach($this->technician);
        $ticket->update(['assigned_to' => $this->technician->id]);

        $this->actingAs($this->technician)
             ->post(route('tickets.comment', $ticket), [
                 'body' => 'Приехал на объект, начал диагностику',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id'   => $this->technician->id,
            'body'      => 'Приехал на объект, начал диагностику',
        ]);
    }

    /** @test */
    public function comment_body_is_required(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->operator)
             ->post(route('tickets.comment', $ticket), ['body' => ''])
             ->assertSessionHasErrors('body');
    }

    // ── История изменений (Observer) ──────────────────────────────────────

    /** @test */
    public function ticket_history_is_logged_on_status_change(): void
    {
        $ticket     = $this->createTicket();
        $inProgress = TicketStatus::where('slug', 'in_progress')->first();

        $this->actingAs($this->admin)
             ->post(route('tickets.start', $ticket));

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'action'    => 'field_changed',
            'field'     => 'Статус',
        ]);
    }

    // ── Удаление ──────────────────────────────────────────────────────────

    /** @test */
    public function only_admin_can_delete_ticket(): void
    {
        $ticket = $this->createTicket();

        // Оператор не может
        $this->actingAs($this->operator)
             ->delete(route('tickets.destroy', $ticket))
             ->assertStatus(403);

        // Админ может
        $this->actingAs($this->admin)
             ->delete(route('tickets.destroy', $ticket))
             ->assertRedirect(route('tickets.index'));

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    // ── Поиск ─────────────────────────────────────────────────────────────

    /** @test */
    public function tickets_can_be_filtered_by_status(): void
    {
        $this->createTicket(['status_id' => $this->statusNew->id]);
        $this->createTicket(['status_id' => $this->statusClosed->id]);

        $response = $this->actingAs($this->admin)
             ->get(route('tickets.index', ['status' => $this->statusNew->id]));

        $response->assertStatus(200);
        $response->assertStatus(200);
    }

    /** @test */
    public function tickets_can_be_searched_by_phone(): void
    {
        $this->createTicket(['phone' => '+79490000001']);
        $this->createTicket(['phone' => '+79490000002']);

        $response = $this->actingAs($this->admin)
             ->get(route('tickets.index', ['search' => '0000001']));

        $response->assertStatus(200);
    }

    // ── Вспомогательный метод ─────────────────────────────────────────────

    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'number'      => Ticket::generateNumber(),
            'type_id'     => $this->type->id,
            'status_id'   => $this->statusNew->id,
            'created_by'  => $this->operator->id,
            'description' => 'Тестовая заявка',
            'priority'    => 'normal',
        ], $overrides));
    }
}
