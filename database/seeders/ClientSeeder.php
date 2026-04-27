<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    /**
     * Crea usuarios con rol "client" usando Spatie.
     */
    public function run(): void
    {
        // ── Cliente fijo de prueba ──────────────────────────────────────────
        $client = User::updateOrCreate(
            ['email' => 'cliente@fixxa.com'],
            [
                'name'      => 'Cliente Demo',
                'password'  => 'password',
                'phone'     => '300-123-4567',
                'city'      => 'Bogotá',
                'address'   => 'Calle 10 # 20-30, Bogotá',
                'type_id'   => 'CC',
                'id_number' => '1000000001',
            ]
        );
        // Sincronizar rol Spatie (evita duplicados si se corre de nuevo)
        $client->syncRoles(['client']);

        // Create client profile if it doesn't exist
        \App\Models\Client::firstOrCreate(
            ['user_id' => $client->id]
        );

        // ── 5 clientes aleatorios ───────────────────────────────────────────
        User::factory()
            ->count(5)
            ->client()
            ->create();
    }
}
