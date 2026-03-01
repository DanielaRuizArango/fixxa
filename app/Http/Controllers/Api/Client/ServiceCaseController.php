<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\CaseImage;
use App\Http\Requests\Api\Client\StoreServiceCaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceCaseController extends Controller
{
    /**
     * Store a newly created service case in storage.
     */
    public function store(StoreServiceCaseRequest $request)
    {
        $validatedData = $request->validated();
        $client = $request->user()->client;

        if (!$client) {
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

        return response()->json([
            'status'  => 'success',
            'message' => 'Caso de servicio creado exitosamente.',
            'data'    => $serviceCase->load('images'),
        ], 201);
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
            ->with(['images', 'responses.technician.user'])
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
    public function show(Request $request, $id)
    {
        $client = $request->user()->client;
        $case = ServiceCase::where('client_id', $client->id)
            ->with(['images', 'responses.technician.user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $case,
        ]);
    }
}
