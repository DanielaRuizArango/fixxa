<?php

namespace Database\Seeders;

use App\Models\ServiceCase;
use App\Models\CaseResponse;
use App\Models\Rating;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener casos que tienen al menos una respuesta
        $casesWithResponses = ServiceCase::has('responses')->get();

        if ($casesWithResponses->isEmpty()) {
            $this->command->warn('No hay casos con respuestas para calificar. Ejecuta ServiceCaseSeeder primero.');
            return;
        }

        $comments = [
            'Excelente servicio, muy puntual y profesional.',
            'El técnico conocía muy bien su trabajo. Recomendado.',
            'Solucionó el problema rápidamente. Muy amable.',
            'Buen trabajo, aunque llegó un poco tarde.',
            'Muy satisfecho con la reparación de mi equipo.',
            'Gran atención al detalle y explicación clara del problema.',
            'Eficiente y educado. Volvería a contratarlo.',
            'Todo quedó perfecto, muchas gracias.',
            'Un poco costoso pero valió la pena por la calidad.',
            'Excelente comunicación durante todo el proceso.'
        ];

        // Vamos a calificar aproximadamente el 60% de los casos que tienen respuestas
        $casesToRate = $casesWithResponses->shuffle()->take($casesWithResponses->count() * 0.6);

        foreach ($casesToRate as $case) {
            // Seleccionar una respuesta al azar para este caso para simular que fue la aceptada
            $response = $case->responses()->inRandomOrder()->first();

            if ($response) {
                // Actualizar el caso como resuelto y asignar el técnico aceptado
                $case->update([
                    'status' => 'resolved',
                    'accepted_technician_id' => $response->technician_id
                ]);

                // Crear la calificación
                Rating::create([
                    'service_case_id' => $case->id,
                    'client_id'       => $case->client_id,
                    'technician_id'   => $response->technician_id,
                    'score'           => rand(4, 5), // Calificaciones mayormente positivas para el demo
                    'comment'         => $comments[array_rand($comments)],
                ]);
            }
        }

        $this->command->info('RatingSeeder ejecutado: ' . $casesToRate->count() . ' casos calificados y resueltos.');
    }
}
