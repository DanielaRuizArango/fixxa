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

        $initialStatuses = ['active', 'pending', 'cancelled'];
        // Cada cliente tiene 3 casos creados
        foreach ($clients as $client) {
            for ($i = 1; $i <= 3; $i++) {
                $case = ServiceCase::create([
                    'client_id' => $client->id,
                    'title' => "Problema con mi equipo - " . $client->user->name . " #$i",
                    'description' => "Hola, tengo un inconveniente con uno de mis equipos y requiero asistencia técnica profesional. Es el caso número $i que reporto.",
                    'service_type' => $i % 2 == 0 ? 'remote' : 'presential',
                    'city' => $client->user->city,
                    'status' => $initialStatuses[array_rand($initialStatuses)],
                ]);

                // Crear 1 o 2 imágenes aleatorias para el caso
                $numImages = rand(1, 2);
                for ($j = 0; $j < $numImages; $j++) {
                    \App\Models\CaseImage::create([
                        'service_case_id' => $case->id,
                        'image_path' => 'https://picsum.photos/seed/' . $case->id . '_' . $j . '/800/600',
                    ]);
                }
            }
        }

        // Obtener todos los casos creados
        $allCases = ServiceCase::all();
        $caseQueue = $allCases->shuffle();
        $caseIndex = 0;
        $totalCases = $caseQueue->count();

        // Cada técnico ha respondido de a cuatro casos
        foreach ($technicians as $technician) {
            // Seleccionar 4 casos para que el técnico responda
            // Para asegurar que todos los casos tengan al menos una respuesta,
            // recorremos la lista de casos de forma circular.
            for ($i = 0; $i < 4; $i++) {
                $case = $caseQueue[$caseIndex % $totalCases];
                $caseIndex++;

                CaseResponse::create([
                    'service_case_id' => $case->id,
                    'technician_id' => $technician->id,
                    'estimated_cost' => rand(80, 250) * 1000,
                    'questions' => "¿Podría indicarme el modelo exacto? ¿El equipo ha sido manipulado anteriormente? Quedo atento a su respuesta.",
                ]);

                // Cambiar el estado del caso a 'responded' o 'resolved'
                if (in_array($case->status, ['active', 'pending'])) {
                    // Posibilidad de que esté resuelto o simplemente respondido
                    $newStatus = rand(0, 1) ? 'responded' : 'resolved';
                    $case->update(['status' => $newStatus]);
                }
            }
        }

        $this->command->info('ServiceCaseSeeder ejecutado con éxito: 3 casos por cliente y 4 respuestas por técnico.');
    }
}
