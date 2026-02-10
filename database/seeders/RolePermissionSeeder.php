<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos
        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view cases',
            'create cases',
            'edit cases',
            'delete cases',
            'respond cases',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Crear roles
        $adminRole = Role::findOrCreate('admin');
        $clientRole = Role::findOrCreate('client');
        $technicianRole = Role::findOrCreate('technician');

        // Asignar permisos a roles
        $adminRole->givePermissionTo(Permission::all());
        
        $clientRole->givePermissionTo([
            'view cases',
            'create cases',
            'edit cases',
            'delete cases',
        ]);

        $technicianRole->givePermissionTo([
            'view cases',
            'respond cases',
        ]);

        // Asignar rol admin al primer usuario (si existe)
        $firstUser = User::first();
        if ($firstUser) {
            // Limpiar roles previos para evitar duplicados si se vuelve a correr
            $firstUser->roles()->detach();
            $firstUser->assignRole('admin');
        }
    }
}

