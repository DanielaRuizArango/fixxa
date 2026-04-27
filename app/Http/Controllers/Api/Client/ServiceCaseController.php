<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\CaseImage;
use App\Http\Requests\Api\Client\StoreServiceCaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Log;

class ServiceCaseController extends Controller
{
    /**
     * Store a newly created service case in storage.
     */
    public function store(StoreServiceCaseRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $client = $request->user()->client;

            if (!$client) {
                Log::warning('Intento de crear caso sin perfil de cliente', ['user_id' => $request->user()->id]);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes un perfil de cliente asociado.',
                ], 403);
            }

            // Crear el caso
            $serviceCase = ServiceCase::create([
                'client_id'   => $client->id,
                'title'       => $validatedData['title'],
                'description' => $validatedData['description'],
                'city'        => $validatedData['city'] ?? $request->user()->city,
                'status'      => 'active',
            ]);

            // Guardar imágenes si fueron enviadas
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('cases/images', 'public');
                    CaseImage::create([
                        'service_case_id' => $serviceCase->id,
                        'image_path'      => $path,
                    ]);
                }
            }

            Log::info('Caso de servicio creado exitosamente', ['case_id' => $serviceCase->id, 'client_id' => $client->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Caso de servicio creado exitosamente.',
                'data'    => $serviceCase->load('images'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear caso de servicio: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el caso de servicio.',
            ], 500);
        }
    }

    /**
     * Display a listing of the client's service cases.
     */
    public function index(Request $request)
    {
        $client = $request->user()->client;

        if (!$client) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes un perfil de cliente asociado.',
            ], 403);
        }

        $cases = ServiceCase::where('client_id', $client->id)
            ->with(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $cases,
        ]);
    }

    /**
     * Display the specified service case.
     */
    public function show(Request $request, $id)
    {
        $client = $request->user()->client;
        $case = ServiceCase::where('client_id', $client->id)
            ->with(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $case,
        ]);
    }
    /**
     * Update the specified service case in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $client = $request->user()->client;
            $serviceCase = ServiceCase::where('client_id', $client->id)->findOrFail($id);
            
            if (!in_array($serviceCase->status, ['active', 'pending'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No puedes modificar un caso que ya ha sido respondido, resuelto o cancelado.',
                ], 403);
            }

            $validatedData = $request->validate([
                'title'       => 'required|string|max:255',
                'description' => 'required|string',
                'city'        => 'nullable|string|max:255',
            ]);

            $serviceCase->update([
                'title'       => $validatedData['title'],
                'description' => $validatedData['description'],
                'city'        => $validatedData['city'] ?? $serviceCase->city,
            ]);

            // Guardar nuevas imágenes si fueron enviadas
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('cases/images', 'public');
                    CaseImage::create([
                        'service_case_id' => $serviceCase->id,
                        'image_path'      => $path,
                    ]);
                }
            }

            Log::info('Caso de servicio actualizado exitosamente', ['case_id' => $serviceCase->id, 'client_id' => $client->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Caso de servicio actualizado exitosamente.',
                'data'    => $serviceCase->load('images'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar caso de servicio: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el caso de servicio.',
            ], 500);
        }
    }

    /**
     * Remove the specified service case from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $client = $request->user()->client;
            $serviceCase = ServiceCase::where('client_id', $client->id)->findOrFail($id);

            if (!in_array($serviceCase->status, ['active', 'pending'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No puedes eliminar un caso que ya ha sido respondido, resuelto o cancelado.',
                ], 403);
            }

            // Eliminar imágenes de storage
            foreach ($serviceCase->images as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }

            $serviceCase->delete();

            Log::info('Caso de servicio eliminado exitosamente', ['case_id' => $id, 'client_id' => $client->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Caso de servicio eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar caso de servicio: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al eliminar el caso de servicio.',
            ], 500);
        }
    }
}
