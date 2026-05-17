<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * El orden importa:
     * 1. RolePermissionSeeder → crea roles y permisos en Spatie PRIMERO.
     * 2. AdminSeeder          → crea el usuario admin y le asigna el rol.
     * 3. ClientSeeder         → crea clientes de prueba con rol 'client'.
     * 4. TechnicianSeeder     → crea técnicos de prueba con rol 'technician'.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminSeeder::class,
            ClientSeeder::class,
            TechnicianSeeder::class,
            ServiceCaseSeeder::class,
            RatingSeeder::class,
            CertificationSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}

