<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Crea usuarios administrativos con diferentes roles.
     */
    public function run(): void
    {
        $admins = [
            [
                'name'      => 'Super Administrador',
                'email'     => 'superadmin@fixxa.com',
                'password'  => 'password',
                'role'      => 'admin', // Atributo en tabla users
                'spatie_role' => 'super_admin'
            ],
            [
                'name'      => 'Administrador Fixxa',
                'email'     => 'admin@fixxa.com',
                'password'  => 'password',
                'role'      => 'admin',
                'spatie_role' => 'admin'
            ],
            [
                'name'      => 'Moderador Fixxa',
                'email'     => 'moderator@fixxa.com',
                'password'  => 'password',
                'role'      => 'admin',
                'spatie_role' => 'moderator'
            ],
        ];

        foreach ($admins as $adminData) {
            $user = User::firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'name'      => $adminData['name'],
                    'password'  => Hash::make($adminData['password']),
                    'role'      => $adminData['role'],
                    'phone'     => '0000000000',
                    'city'      => 'Admin City',
                    'address'   => 'Admin Address',
                    'type_id'   => 'CC',
                    'id_number' => 'ID-' . rand(1000, 9999),
                    'status'    => 'active',
                ]
            );

            // Sincronizar rol Spatie
            $user->syncRoles([$adminData['spatie_role']]);

            // Crear perfil de admin si no existe
            \App\Models\Admin::firstOrCreate(
                ['user_id' => $user->id]
            );
        }
    }
}
