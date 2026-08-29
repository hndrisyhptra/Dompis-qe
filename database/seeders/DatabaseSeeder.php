<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Satu akun per role untuk tes login manual (kredensial tetap/dikenal,
     * bukan data acak factory). Pakai updateOrCreate supaya
     * `php artisan db:seed` aman dijalankan berulang kali.
     */
    public function run(): void
    {
        $accounts = [
            UserRole::SUPER_ADMIN->value => 'superadmin',
            UserRole::ADMIN->value => 'admin',
            UserRole::TEKNISI->value => 'teknisi',
            UserRole::MANAGER->value => 'manager',
            UserRole::APPROVER->value => 'approver',
        ];

        foreach ($accounts as $roleCode => $username) {
            $roleId = Role::where('code', $roleCode)->value('id');

            User::updateOrCreate(
                ['username' => $username],
                [
                    'role_id' => $roleId,
                    'name' => UserRole::from($roleCode)->label().' Test',
                    'email' => "{$username}@dompis.test",
                    'status' => 'active',
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
