<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetUserPassword extends Command
{
    protected $signature   = 'helpdesk:set-password {login : Логин или email пользователя} {password : Новый пароль}';
    protected $description = 'Установить пароль пользователю по логину или email';

    public function handle(): int
    {
        $identifier = $this->argument('login');
        $password   = $this->argument('password');

        $user = User::where('login', $identifier)->orWhere('email', $identifier)->first();

        if (! $user) {
            $this->error("Пользователь «{$identifier}» не найден.");
            return 1;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Пароль для {$user->name} ({$user->login}) успешно обновлён.");
        return 0;
    }
}
