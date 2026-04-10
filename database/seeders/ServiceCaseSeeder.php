<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ServiceCase;
use App\Models\Technician;
use App\Models\CaseResponse;
use Illuminate\Database\Seeder;

class ServiceCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::all();
        $technicians = Technician::all();

        if ($clients->isEmpty() || $technicians->isEmpty()) {
            $this->command->warn('No hay suficientes clientes o técnicos para sembrar casos. Asegúrate de ejecutar ClientSeeder y TechnicianSeeder primero.');
            return;
        }

        // Cada cliente tiene 2 casos creados
        foreach ($clients as $client) {
            for ($i = 1; $i <= 2; $i++) {
                ServiceCase::create([
                    'client_id' => $client->id,
                    'title' => "Problema con mi equipo - " . $client->user->name . " #$i",
                    'description' => "Hola, tengo un inconveniente con uno de mis equipos y requiero asistencia técnica profesional. Es el caso número $i que reporto.",
                    'city' => $client->user->city,
                    'status' => 'active',
                ]);
            }
        }

        // Obtener todos los casos creados
        $allCases = ServiceCase::all();

        // Cada técnico ha respondido de a dos casos
        foreach ($technicians as $technician) {
            // Seleccionar 2 casos aleatorios para que el técnico responda
            // Usamos shuffle() y take(2) para asegurar variedad
            $casesToRespond = $allCases->shuffle()->take(2);

            foreach ($casesToRespond as $case) {
                CaseResponse::create([
                    'service_case_id' => $case->id,
                    'technician_id' => $technician->id,
                    'estimated_cost' => rand(80, 250) * 1000, // Precios entre 80k y 250k
                    'questions' => "¿Podría indicarme el modelo exacto? ¿El equipo ha sido manipulado anteriormente? Quedo atento a su respuesta.",
                ]);

                // Cambiar el estado del caso a 'responded'
                if ($case->status === 'active') {
                    $case->update(['status' => 'responded']);
                }
            }
        }

        $this->command->info('ServiceCaseSeeder ejecutado con éxito: 2 casos por cliente y 2 respuestas por técnico.');
    }
}
