<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ServiceCase;
use App\Models\Technician;
use App\Models\Rating;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SystemAlertTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = Client::first();
        $technician = Technician::first();

        if (!$client || !$technician) {
            $this->command->error('Run ClientSeeder and TechnicianSeeder first.');
            return;
        }

        // 1. Create a case that is 48 hours old and has no responses
        ServiceCase::create([
            'client_id' => $client->id,
            'title' => 'URGENTE: Caso abandonado para pruebas',
            'description' => 'Este caso fue creado hace 48 horas y no tiene respuestas.',
            'city' => $client->user->city,
            'status' => 'active',
            'created_at' => Carbon::now()->subHours(48),
        ]);

        // 2. Create another case that is 5 hours old (should NOT trigger alert)
        ServiceCase::create([
            'client_id' => $client->id,
            'title' => 'Caso reciente para pruebas',
            'description' => 'Este caso es reciente y no debería alertar todavía.',
            'city' => $client->user->city,
            'status' => 'active',
            'created_at' => Carbon::now()->subHours(5),
        ]);

        // 3. Create a technician with poor ratings
        // We'll use the first technician for simplicity, or create a new one
        $badTech = Technician::latest()->first(); 
        
        // Add 3 poor ratings
        for ($i = 0; $i < 3; $i++) {
            Rating::create([
                'service_case_id' => ServiceCase::first()->id, // Just link to any case
                'client_id' => $client->id,
                'technician_id' => $badTech->id,
                'score' => rand(1, 2),
                'comment' => 'Muy mal servicio, no lo recomiendo.',
            ]);
        }

        $this->command->info('SystemAlertTestSeeder ejecutado. Se creó 1 caso abandonado y 1 técnico con malas calificaciones.');
    }
}
