<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin {email} {--name=Administrator}', function () {
    $password = $this->secret('Password (minimum 10 characters)');

    if (! is_string($password) || strlen($password) < 10) {
        $this->error('The password must contain at least 10 characters.');

        return 1;
    }

    User::query()->updateOrCreate(
        ['email' => strtolower($this->argument('email'))],
        [
            'name' => $this->option('name'),
            'password' => Hash::make($password),
            'is_admin' => true,
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'approved_at' => now(),
        ],
    );

    $this->info('Administrator account created.');

    return 0;
})->purpose('Create or promote an administrator account');

Schedule::command('dns:expire')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('dns:purge-deletion-holds')->dailyAt('01:30')->withoutOverlapping();
