<?php

namespace Tests\Unit;

use App\Models\{Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionsTest extends TestCase
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
        $this->users['admin'] = User::whereHas('role', fn($q) => $q->where('slug','admin'))->first();
    }

    // ── isAdmin / isOperator etc ──────────────────────────────────────────

    /** @test */
    public function role_helpers_return_correct_values(): void
    {
        $this->assertTrue($this->users['admin']->isAdmin());
        $this->assertFalse($this->users['operator']->isAdmin());

        $this->assertTrue($this->users['head_support']->isHeadSupport());
        $this->assertFalse($this->users['admin']->isHeadSupport());

        $this->assertTrue($this->users['operator']->isOperator());
        $this->assertTrue($this->users['foreman']->isForeman());
        $this->assertTrue($this->users['technician']->isTechnician());
    }

    // ── canManageSettings ─────────────────────────────────────────────────

    /** @test */
    public function admin_and_head_support_can_manage_settings(): void
    {
        $this->assertTrue($this->users['admin']->canManageSettings());
        $this->assertTrue($this->users['head_support']->canManageSettings());
        $this->assertFalse($this->users['operator']->canManageSettings());
        $this->assertFalse($this->users['foreman']->canManageSettings());
        $this->assertFalse($this->users['technician']->canManageSettings());
    }

    // ── hasPermission ─────────────────────────────────────────────────────

    /** @test */
    public function admin_has_wildcard_permission(): void
    {
        $admin = $this->users['admin'];
        $this->assertTrue($admin->hasPermission('tickets.view'));
        $this->assertTrue($admin->hasPermission('tickets.delete'));
        $this->assertTrue($admin->hasPermission('settings.anything'));
        $this->assertTrue($admin->hasPermission('completely.made.up.permission'));
    }

    /** @test */
    public function operator_has_ticket_permissions(): void
    {
        $operator = $this->users['operator'];
        $this->assertTrue($operator->hasPermission('tickets.view'));
        $this->assertTrue($operator->hasPermission('tickets.create'));
        $this->assertFalse($operator->hasPermission('settings.edit'));
        $this->assertFalse($operator->hasPermission('users.operators.delete'));
    }

    /** @test */
    public function technician_has_limited_permissions(): void
    {
        $tech = $this->users['technician'];
        $this->assertTrue($tech->hasPermission('tickets.view'));
        $this->assertTrue($tech->hasPermission('tickets.start'));
        $this->assertTrue($tech->hasPermission('tickets.close'));
        $this->assertTrue($tech->hasPermission('tickets.comment'));
        $this->assertFalse($tech->hasPermission('tickets.create'));
        $this->assertFalse($tech->hasPermission('tickets.delete'));
        $this->assertFalse($tech->hasPermission('settings.view'));
    }

    /** @test */
    public function foreman_can_assign_tickets(): void
    {
        $foreman = $this->users['foreman'];
        $this->assertTrue($foreman->hasPermission('tickets.assign'));
        $this->assertTrue($foreman->hasPermission('tickets.close'));
        $this->assertFalse($foreman->hasPermission('settings.edit'));
    }

    /** @test */
    public function head_support_manages_operators(): void
    {
        $head = $this->users['head_support'];
        $this->assertTrue($head->hasPermission('users.operators.create'));
        $this->assertTrue($head->hasPermission('users.operators.delete'));
        $this->assertTrue($head->hasPermission('tickets.view'));
        $this->assertTrue($head->hasPermission('settings.edit'));
    }
}
