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
        $client = User::firstOrCreate(
            ['email' => 'cliente@fixxa.com'],
            [
                'name'     => 'Cliente Demo',
                'password' => Hash::make('password'),
                'role'     => 'client',
            ]
        );
        // Sincronizar rol Spatie (evita duplicados si se corre de nuevo)
        $client->syncRoles(['client']);

        // Create client profile if it doesn't exist
        \App\Models\Client::firstOrCreate(
            ['user_id' => $client->id],
            [
                'phone'    => '300-123-4567',
                'address'  => 'Calle 10 # 20-30, Bogotá',
                'cedula'   => '1000000001',
            ]
        );

        // ── 5 clientes aleatorios ───────────────────────────────────────────
        User::factory()
            ->count(5)
            ->client()
            ->create();
    }
}
