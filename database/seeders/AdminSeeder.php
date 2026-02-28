<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Crea el usuario administrador con rol "admin" usando Spatie.
     */
    public function run(): void
    {
        // ── Administrador fijo ──────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@fixxa.com'],
            [
                'name'      => 'Administrador Fixxa',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'phone'     => '0000000000',
                'city'      => 'Admin City',
                'address'   => 'Admin Address',
                'type_id'   => 'CC',
                'id_number' => '1234567890',
            ]
        );

        // Sincronizar rol Spatie (evita duplicados si se corre de nuevo)
        $admin->syncRoles(['admin']);

        // Create admin profile if it doesn't exist
        \App\Models\Admin::firstOrCreate(
            ['user_id' => $admin->id]
        );
    }
}
