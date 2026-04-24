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

        return response()->json([
            'status'  => 'success',
            'message' => 'Calificación enviada.',
            'data'    => $rating,
        ], 201);
    }
}
