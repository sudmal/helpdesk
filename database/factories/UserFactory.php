<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => fake()->phoneNumber(),
            'password'          => Hash::make('password'),
            'role_id'           => Role::inRandomOrder()->value('id') ?? 1,
            'is_active'         => true,
            'notify_email'      => true,
            'notify_telegram'   => false,
            'telegram_chat_id'  => null,
            'remember_token'    => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function admin(): static
    {
        return $this->state(fn() => [
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn() => [
            'role_id' => Role::where('slug', 'operator')->value('id'),
        ]);
    }

    public function technician(): static
    {
        return $this->state(fn() => [
            'role_id' => Role::where('slug', 'technician')->value('id'),
        ]);
    }
}
