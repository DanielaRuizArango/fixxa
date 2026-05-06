<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\CaseResponse;
use App\Http\Requests\Api\Technician\StoreCaseResponseRequest;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

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
     * Display a listing of available service cases for technicians.
     */
    public function availableCases(Request $request)
    {
        $query = ServiceCase::whereIn('status', ['active', 'responded'])
            ->with(['images', 'client.user', 'responses']);

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

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'responses_count') {
            $query->withCount('responses')->orderBy('responses_count', $sortOrder);
        } elseif ($sortBy === 'client_name') {
            $query->join('clients', 'service_cases.client_id', '=', 'clients.id')
                  ->join('users', 'clients.user_id', '=', 'users.id')
                  ->select('service_cases.*')
                  ->orderBy('users.name', $sortOrder);
        } elseif ($sortBy === 'city') {
            $query->orderBy('city', $sortOrder);
        } elseif ($request->filled('radius') && $request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->radius; // En kilómetros

            // Fórmula Haversine para calcular distancia en SQL
            $query->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
                  ->having('distance', '<=', $radius)
                  ->orderBy('distance');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $cases = $query->paginate(10);

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
