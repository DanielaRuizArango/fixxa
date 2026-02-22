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
                'name'     => 'Administrador Fixxa',
                'phone'    => '300-000-0000',
                'address'  => 'Sede Principal, Bogotá',
                'cedula'   => '9999999999',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // Sincronizar rol Spatie (evita duplicados si se corre de nuevo)
        $admin->syncRoles(['admin']);
    }
}
