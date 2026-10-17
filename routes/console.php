<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cfo:create-admin {email} {--name=}', function () {
    $email = strtolower($this->argument('email'));
    $name = $this->option('name') ?: $this->ask('Administrator full name');
    $password = $this->secret('Password (8+ characters, upper/lower case, number, symbol)');
    $username = \Illuminate\Support\Str::before($email, '@').'@cfo.office.com';
    $data = ['name' => $name, 'email' => $email, 'password' => $password, 'system_username' => $username];
    $validator = validator($data, ['name' => 'required|string|max:160', 'email' => 'required|email|unique:users', 'system_username' => 'unique:users', 'password' => ['required', \App\Http\Controllers\AuthController::passwordRule()]]);
    if ($validator->fails()) { $this->error($validator->errors()->first()); return 1; }
    $parts = explode(' ', $name, 2);
    \App\Models\User::create([...$data, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'role' => 'Admin', 'status' => 'Approved']);
    $this->info('Administrator created. Sign in with '.$email.'.');
})->purpose('Provision an approved administrator locally; public registration requires approval');
