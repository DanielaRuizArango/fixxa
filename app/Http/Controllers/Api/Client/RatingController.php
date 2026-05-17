<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\StoreRatingRequest;
use App\Models\Rating;
use App\Models\ServiceCase;

class RatingController extends Controller
{
    /**
     * Store a new rating for a resolved service case.
     */
    public function store(StoreRatingRequest $request)
    {
        $client = $request->user()->client;

        if (!$client) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes un perfil de cliente asociado.',
            ], 403);
        }

        $serviceCase = ServiceCase::where('client_id', $client->id)
            ->find($request->service_case_id);

        if (!$serviceCase) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El caso no existe o no te pertenece.',
            ], 403);
        }

        if ($serviceCase->status !== 'resolved') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Solo puedes calificar casos resueltos.',
            ], 422);
        }

        if (Rating::where('service_case_id', $serviceCase->id)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Este caso ya tiene una calificación.',
            ], 422);
        }

        if (!$serviceCase->accepted_technician_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El caso no tiene un técnico asignado.',
            ], 422);
        }

        $rating = Rating::create([
            'service_case_id' => $serviceCase->id,
            'client_id'       => $client->id,
            'technician_id'   => $serviceCase->accepted_technician_id,
            'score'           => $request->score,
            'comment'         => $request->comment,
        ]);

        // Log action for administrators
        \App\Utils\AuditLogger::log(
            'submit_rating',
            'App\\Models\\Rating',
            $rating->id,
            "El cliente {$request->user()->name} calificó al técnico con un puntaje de {$rating->score}/5 en el caso #{$serviceCase->id}."
        );

        // Notificar al técnico
        $technicianUser = $serviceCase->acceptedTechnician->user;
        if ($technicianUser) {
            try {
                $technicianUser->notify(new \App\Notifications\TechnicianRated($rating, $client, $serviceCase));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error enviando notificación de calificación: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Calificación enviada.',
            'data'    => $rating,
        ], 201);
    }
}
