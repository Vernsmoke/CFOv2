<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (config('cfo.demo_accounts.enabled')) {
            $this->call(DemoAccountSeeder::class);

            return;
        }

        $email = strtolower(trim((string) config('cfo.admin.email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Set CFO_DEFAULT_ADMIN_EMAIL to a valid administrator email before seeding.');
        }
        $existing = User::where('email', $email)->first();
        if ($existing && ! $existing->isSuperAdmin()) {
            throw new \RuntimeException('CFO_DEFAULT_ADMIN_EMAIL is already used by a non-Super Admin account. Choose a different email.');
        }

        $identityCollision = User::where('id', '!=', $existing?->id ?? 0)->where(function ($query) use ($email) {
            $query->where('email', $email)
                ->orWhere('original_email', $email)
                ->orWhere('login_email', $email)
                ->orWhere('system_username', $email);
        })->exists();
        if ($identityCollision) {
            throw new \RuntimeException('CFO_DEFAULT_ADMIN_EMAIL conflicts with a student login. Choose a different administrator email.');
        }

        if ($existing) {
            return;
        }

        $password = (string) config('cfo.admin.password');
        if ($password === '') {
            throw new \RuntimeException('Set CFO_DEFAULT_ADMIN_PASSWORD before seeding the first administrator.');
        }

        User::firstOrCreate(['email' => $email], [
            'name' => 'CFO Administrator',
            'first_name' => 'CFO',
            'middle_name' => null,
            'last_name' => 'Administrator',
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Approved',
            'password' => Hash::make($password),
        ]);
    }
}
