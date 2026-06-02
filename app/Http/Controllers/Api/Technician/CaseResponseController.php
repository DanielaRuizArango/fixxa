<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\CaseResponse;
use App\Http\Requests\Api\Technician\StoreCaseResponseRequest;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use App\Utils\AuditLogger;

class CaseResponseController extends Controller
{
    /**
     * Store a newly created case response in storage.
     */
    public function store(StoreCaseResponseRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $technician = $request->user()->technician;

            if (!$technician) {
                Log::warning('Intento de enviar respuesta sin perfil de técnico', ['user_id' => $request->user()->id]);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes un perfil de técnico asociado.',
                ], 403);
            }

            // Crear la respuesta del técnico (cotización/propuesta)
            $response = CaseResponse::create([
                'service_case_id' => $validatedData['service_case_id'],
                'technician_id'   => $technician->id,
                'estimated_cost'  => $validatedData['estimated_cost'],
                'questions'       => $validatedData['questions'],
            ]);

            // Cambiar el estado del caso a "responded" (opcional, dependiendo de la lógica de negocio)
            $serviceCase = ServiceCase::find($validatedData['service_case_id']);
            if ($serviceCase && $serviceCase->status === 'active') {
                $serviceCase->update(['status' => 'responded']);
            }

            // Notificar al cliente
            $clientUser = $serviceCase->client->user;
            if ($clientUser) {
                try {
                    $clientUser->notify(new \App\Notifications\CaseResponded($response, $technician, $serviceCase));
                } catch (\Exception $e) {
                    Log::error('Error enviando notificación de respuesta a caso: ' . $e->getMessage());
                }
            }

            Log::info('Respuesta de caso enviada exitosamente', [
                'response_id' => $response->id,
                'technician_id' => $technician->id,
                'case_id' => $validatedData['service_case_id']
            ]);

            // Log action for administrators
            AuditLogger::log(
                'send_proposal',
                'App\\Models\\CaseResponse',
                $response->id,
                "El técnico {$request->user()->name} envió una propuesta de costo estimado \$" . number_format($response->estimated_cost) . " para el caso #{$response->service_case_id}."
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Respuesta enviada exitosamente.',
                'data'    => $response->load('serviceCase'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al enviar respuesta de caso: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al enviar la respuesta.',
            ], 500);
        }
    }

    /**
     * Update an existing case response (proposal) in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $technician = $request->user()->technician;
            if (!$technician) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes un perfil de técnico asociado.',
                ], 403);
            }

            $response = CaseResponse::where('technician_id', $technician->id)
                ->findOrFail($id);

            $serviceCase = $response->serviceCase;

            // Lógica de validación:
            // que pueda editar la propuesta si el cliente no la ha aceptado y el caso no esta resuelto/cancelado
            if ($serviceCase->accepted_technician_id === $technician->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No puedes editar la propuesta porque ya fue aceptada por el cliente.',
                ], 403);
            }

            if (in_array($serviceCase->status, ['resolved', 'cancelled'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No puedes editar la propuesta porque el caso ya está resuelto o cancelado.',
                ], 403);
            }

            $request->validate([
                'estimated_cost' => 'required|numeric|min:0',
                'questions'      => 'nullable|string',
            ]);

            $response->update([
                'estimated_cost' => $request->estimated_cost,
                'questions'      => $request->questions,
            ]);

            // Registrar log de auditoría
            AuditLogger::log(
                'edit_proposal',
                'App\\Models\\CaseResponse',
                $response->id,
                "El técnico {$request->user()->name} editó su propuesta para el caso #{$response->service_case_id}. Nuevo costo: \$" . number_format($response->estimated_cost) . "."
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Propuesta actualizada exitosamente.',
                'data'    => $response->load('serviceCase'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar propuesta: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar la propuesta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of available service cases for technicians.
     */
    public function availableCases(Request $request)
    {
        $query = ServiceCase::whereIn('status', ['active', 'responded']);

        // Filtro por búsqueda (Título o Descripción)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por ciudad
        if ($request->filled('city')) {
            $query->where('city', 'LIKE', "%{$request->city}%");
        }

        // Filtro por tipo de servicio
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        // Filtro por radio de cercanía (km)
        if ($request->filled('radius') && $request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->radius; // En kilómetros

            // Fórmula Haversine para calcular distancia en SQL con whereRaw (compatible con count() de paginación)
            $formula = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
            $query->whereRaw("{$formula} <= ?", [$lat, $lng, $lat, $radius])
                  ->selectRaw("service_cases.*, {$formula} AS distance", [$lat, $lng, $lat]);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'responses_count') {
            $query->withCount('responses')->orderBy('responses_count', $sortOrder);
        } elseif ($sortBy === 'client_name') {
            $direction = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
            $query->orderByRaw("(SELECT u.name FROM clients c JOIN users u ON c.user_id = u.id WHERE c.id = service_cases.client_id LIMIT 1) {$direction}");
        } elseif ($sortBy === 'city') {
            $query->orderBy('city', $sortOrder);
        } else {
            // Si el filtro de radio está activo y no se especifica otro orden diferente a created_at, ordenar por cercanía (distancia asc)
            if ($request->filled('radius') && $request->filled('lat') && $request->filled('lng') && $sortBy === 'created_at') {
                $query->orderBy('distance', 'asc');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        $cases = ServiceCase::query()
            ->fromSub($query, 'service_cases')
            ->with(['images', 'client.user', 'responses'])
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $cases,
        ]);
    }

    /**
     * Display the specified service case.
     */
    public function showCase($id)
    {
        $case = ServiceCase::with(['images', 'client.user', 'responses.technician.user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $case,
        ]);
    }

    /**
     * Display the authenticated technician's responses.
     */
    public function myResponses(Request $request)
    {
        $technician = $request->user()->technician;
        $query = CaseResponse::where('technician_id', $technician->id)
            ->with('serviceCase');

        // Búsqueda por título del caso relacionado
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('serviceCase', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        // Filtro por estado del caso relacionado
        if ($request->has('status') && $request->status != '') {
            $query->whereHas('serviceCase', function($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        $responses = $query->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $responses,
        ]);
    }
}
