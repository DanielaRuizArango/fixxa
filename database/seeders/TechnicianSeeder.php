<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TechnicianSeeder extends Seeder
{
    /**
     * Crea usuarios con rol "technician" usando Spatie.
     */
    public function run(): void
    {
        // ── Técnico fijo de prueba ──────────────────────────────────────────
        $technician = User::updateOrCreate(
            ['email' => 'tecnico@fixxa.com'],
            [
                'name'      => 'Técnico Demo',
                'password'  => 'password',
                'role'      => 'technician',
                'phone'     => '300-987-6543',
                'city'      => 'Medellín',
                'address'   => 'Carrera 5 # 10-15, Medellín',
                'type_id'   => 'CC',
                'id_number' => '2000000001',
            ]
        );
        // Sincronizar rol Spatie (evita duplicados si se corre de nuevo)
        $technician->syncRoles(['technician']);

        // Create technician profile if it doesn't exist
        \App\Models\Technician::firstOrCreate(
            ['user_id' => $technician->id],
            [
                'experience' => 'Más de 5 años de experiencia en mantenimiento de equipos electrónicos y de refrigeración.',
                'title'      => 'Técnico en Refrigeración',
            ]
        );

        // ── 5 técnicos aleatorios ───────────────────────────────────────────
        User::factory()
            ->count(5)
            ->technician()
            ->create();
    }
}
