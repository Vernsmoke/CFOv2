<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts may only be seeded in local or testing environments.');
        }

        $password = (string) config('cfo.demo_accounts.password');
        if ($password === '') {
            throw new \RuntimeException('Set CFO_DEMO_ACCOUNTS_PASSWORD before seeding demo accounts.');
        }

        User::updateOrCreate(['email' => 'interface.admin@cfo.office.com'], [
            'name' => 'Interface Review Admin',
            'system_username' => 'interface.admin@cfo.office.com',
            'original_email' => 'interface.admin@cfo.office.com',
            'login_email' => null,
            'first_name' => 'Interface Review',
            'middle_name' => null,
            'last_name' => 'Admin',
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Approved',
            'password' => Hash::make($password),
            'password_change_required' => false,
            'password_change_expires_at' => null,
        ]);

        User::updateOrCreate(['email' => 'interface.staff@cfo.office.com'], [
            'name' => 'Interface Review Staff',
            'student_id' => 'DEMO-STAFF',
            'system_username' => 'interface.staff@cfo.office.com',
            'original_email' => 'interface.staff@cfo.office.com',
            'login_email' => 'interface.staff@cfo.office.com',
            'first_name' => 'Interface Review',
            'middle_name' => null,
            'last_name' => 'Staff',
            'role' => User::ROLE_CFO_STAFF,
            'status' => 'Approved',
            'password' => Hash::make($password),
            'password_change_required' => false,
            'password_change_expires_at' => null,
        ]);

        User::updateOrCreate(['email' => 'interface.student@gmail.com'], [
            'name' => 'Interface Review Student',
            'student_id' => 'DEMO-0001',
            'system_username' => 'interface.student@cfo.office.com',
            'original_email' => 'interface.student@gmail.com',
            'login_email' => 'interface.student@cfo.office.com',
            'first_name' => 'Interface Review',
            'middle_name' => null,
            'last_name' => 'Student',
            'role' => 'Student',
            'status' => 'Approved',
            'department' => 'College of Criminology',
            'course' => 'Bachelor of Science in Criminology',
            'major' => null,
            'year_level' => '1',
            'section' => 'A',
            'password' => Hash::make($password),
            'password_change_required' => false,
            'password_change_expires_at' => null,
        ]);
    }
}
