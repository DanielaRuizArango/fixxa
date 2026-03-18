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
    public function availableCases()
    {
        // Listar casos activos que NO han sido resueltos o cancelados
        $cases = ServiceCase::whereIn('status', ['active', 'responded'])
            ->with(['images', 'client.user'])
            ->latest()
            ->get();

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
        $responses = CaseResponse::where('technician_id', $technician->id)
            ->with('serviceCase')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $responses,
        ]);
    }
}
