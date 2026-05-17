<?php

namespace Database\Seeders;

use App\Models\ServiceCase;
use App\Models\CaseResponse;
use App\Models\Rating;
use App\Models\Conversation;
use App\Models\Message;
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

                // Crear conversación entre cliente y técnico para este caso
                $conversation = Conversation::create([
                    'service_case_id' => $case->id,
                    'client_id'       => $case->client_id,
                    'technician_id'   => $response->technician_id,
                ]);

                // Obtener ID de usuario del cliente y técnico
                $clientUserId = $case->client->user_id;
                $techUserId = $response->technician->user_id;

                // Crear secuencia de chat simulada
                $chatSequence = [
                    ['sender_id' => $clientUserId, 'message' => "Hola, he aceptado tu propuesta para reparar mi equipo. ¿Cuándo podrías venir?"],
                    ['sender_id' => $techUserId, 'message' => "¡Hola! Qué bueno saberlo. Puedo ir hoy mismo por la tarde, tipo 3:00 PM. ¿Te sirve?"],
                    ['sender_id' => $clientUserId, 'message' => "Sí, perfecto. Esa hora me queda muy bien. Aquí te espero."],
                    ['sender_id' => $techUserId, 'message' => "Excelente, nos vemos a las 3:00 PM. Saludos."],
                    ['sender_id' => $techUserId, 'message' => "Ya voy llegando a tu dirección, estoy afuera."],
                    ['sender_id' => $clientUserId, 'message' => "Listo, ya te abro la puerta. Muchas gracias."],
                ];

                foreach ($chatSequence as $msgData) {
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'sender_id'       => $msgData['sender_id'],
                        'message'         => $msgData['message'],
                        'is_read'         => true,
                    ]);
                }
            }
        }

        $this->command->info('RatingSeeder ejecutado: ' . $casesToRate->count() . ' casos calificados y resueltos.');
    }
}
