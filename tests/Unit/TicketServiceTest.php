<?php

namespace Tests\Unit;

use App\Models\{Role, User, Ticket, TicketType, TicketStatus, Brigade, Address};
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;
    private User $user;
    private TicketType $type;
    private TicketStatus $statusNew;
    private TicketStatus $statusInProgress;
    private TicketStatus $statusClosed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->service         = new TicketService();
        $this->user            = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->first();
        $this->type            = TicketType::first();
        $this->statusNew       = TicketStatus::where('slug', 'new')->first();
        $this->statusInProgress = TicketStatus::where('slug', 'in_progress')->first();
        $this->statusClosed    = TicketStatus::where('slug', 'closed')->first();
    }

    /** @test */
    public function create_generates_unique_ticket_number(): void
    {
        $t1 = $this->service->create([
            'type_id'     => $this->type->id,
            'description' => 'Первая заявка',
            'priority'    => 'normal',
        ], $this->user);

        $t2 = $this->service->create([
            'type_id'     => $this->type->id,
            'description' => 'Вторая заявка',
            'priority'    => 'normal',
        ], $this->user);

        $this->assertNotEquals($t1->number, $t2->number);
        $this->assertMatchesRegularExpression('/^Т-\d+$/', $t1->number);
        $this->assertMatchesRegularExpression('/^Т-\d+$/', $t2->number);
    }

    /** @test */
    public function create_sets_new_status_automatically(): void
    {
        $ticket = $this->service->create([
            'type_id'     => $this->type->id,
            'description' => 'Проверка статуса',
            'priority'    => 'normal',
        ], $this->user);

        $this->assertEquals('new', $ticket->status->slug);
    }

    /** @test */
    public function create_sets_creator(): void
    {
        $ticket = $this->service->create([
            'type_id'     => $this->type->id,
            'description' => 'Проверка создателя',
            'priority'    => 'normal',
        ], $this->user);

        $this->assertEquals($this->user->id, $ticket->created_by);
    }

    /** @test */
    public function update_status_to_in_progress_sets_started_at(): void
    {
        $ticket = $this->makeTicket();

        $updated = $this->service->updateStatus($ticket, 'in_progress', $this->user);

        $this->assertEquals('in_progress', $updated->status->slug);
        $this->assertNotNull($updated->fresh()->started_at);
    }

    /** @test */
    public function update_status_to_paused_sets_paused_at(): void
    {
        $ticket = $this->makeTicket(['status_id' => $this->statusInProgress->id]);

        $updated = $this->service->updateStatus($ticket, 'paused', $this->user);

        $this->assertEquals('paused', $updated->status->slug);
        $this->assertNotNull($updated->fresh()->paused_at);
    }

    /** @test */
    public function update_status_to_closed_sets_closed_at(): void
    {
        $ticket = $this->makeTicket();

        $this->service->updateStatus($ticket, 'closed', $this->user, 'Всё починили');

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status->slug);
        $this->assertNotNull($ticket->closed_at);
        $this->assertEquals('Всё починили', $ticket->close_notes);
    }

    /** @test */
    public function update_status_logs_history(): void
    {
        $ticket = $this->makeTicket();

        $this->service->updateStatus($ticket, 'closed', $this->user);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'user_id'   => $this->user->id,
            'action'    => 'field_changed',
        ]);
    }

    /** @test */
    public function assign_brigade_logs_history(): void
    {
        $ticket  = $this->makeTicket();
        $brigade = Brigade::create(['name' => 'Тест']);

        $this->service->assign($ticket, $brigade->id, null, $this->user);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'action'    => 'assigned',
            'new_value' => 'Тест',
        ]);
    }

    /** @test */
    public function assign_updates_brigade_on_ticket(): void
    {
        $ticket  = $this->makeTicket();
        $brigade = Brigade::create(['name' => 'Новая бригада']);

        $this->service->assign($ticket, $brigade->id, null, $this->user);

        $this->assertDatabaseHas('tickets', [
            'id'         => $ticket->id,
            'brigade_id' => $brigade->id,
        ]);
    }

    /** @test */
    public function generate_number_format_is_correct(): void
    {
        $number = Ticket::generateNumber();
        $this->assertMatchesRegularExpression('/^Т-\d{6}$/', $number);
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function makeTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'number'      => Ticket::generateNumber(),
            'type_id'     => $this->type->id,
            'status_id'   => $this->statusNew->id,
            'created_by'  => $this->user->id,
            'description' => 'Тестовая заявка',
            'priority'    => 'normal',
        ], $overrides));
    }
}
