<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** @test */
    public function login_page_loads(): void
    {
        $this->get(route('login'))->assertStatus(200);
    }
}
