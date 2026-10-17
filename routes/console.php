<?php

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cfo:create-super-admin {email} {--name=}', function () {
    $email = strtolower($this->argument('email'));
    $name = $this->option('name') ?: $this->ask('Administrator full name');
    $password = $this->secret('Password (8+ characters, upper/lower case, number, symbol)');
    $username = Str::before($email, '@').'@cfo.office.com';
    $data = ['name' => $name, 'email' => $email, 'password' => $password, 'system_username' => $username];
    $validator = validator($data, ['name' => 'required|string|max:160', 'email' => 'required|email|unique:users', 'system_username' => 'unique:users', 'password' => ['required', AuthController::passwordRule()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $parts = explode(' ', $name, 2);
    User::create([...$data, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'role' => User::ROLE_SUPER_ADMIN, 'status' => 'Approved']);
    $this->info('Super Admin created. Sign in with '.$email.'.');
})->purpose('Provision an approved Super Admin locally');
