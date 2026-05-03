<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /** @test */
    public function login_page_is_accessible(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_login_with_valid_credentials(): void
    {
        $response = $this->post(route('login'), [
            'email'    => 'admin@helpdesk.local',
            'password' => 'Admin1234!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    /** @test */
    public function user_cannot_login_with_wrong_password(): void
    {
        $this->post(route('login'), [
            'email'    => 'admin@helpdesk.local',
            'password' => 'wrongpassword',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** @test */
    public function inactive_user_cannot_login(): void
    {
        $role = Role::where('slug', 'operator')->first();
        $user = User::factory()->create([
            'role_id'   => $role->id,
            'is_active' => false,
            'password'  => bcrypt('password123'),
        ]);

        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** @test */
    public function authenticated_user_can_logout(): void
    {
        $user = User::where('email', 'admin@helpdesk.local')->first();

        $this->actingAs($user)
             ->post(route('logout'))
             ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** @test */
    public function guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))
             ->assertRedirect(route('login'));
    }
}
