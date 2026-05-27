<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Http\Requests\Api\Admin\UpdateCaseStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceCaseController extends Controller
{
    /**
     * Display a listing of all service cases.
     */
    public function index(Request $request)
    {
        try {
            $query = ServiceCase::with(['client.user', 'responses.technician.user', 'images', 'rating', 'acceptedTechnician.user']);

            // Búsqueda por título o descripción
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtro por estado
            if ($request->has('status') && $request->status != '') {
                $query->where('status', $request->status);
            }

            // Filtro por ciudad
            if ($request->has('city') && $request->city != '') {
                $query->where('city', $request->city);
            }

            // Filtro por tipo de servicio
            if ($request->has('service_type') && $request->service_type != '') {
                $query->where('service_type', $request->service_type);
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
            } elseif ($sortBy === 'technician_name') {
                $query->leftJoin('technicians', 'service_cases.accepted_technician_id', '=', 'technicians.id')
                      ->leftJoin('users', 'technicians.user_id', '=', 'users.id')
                      ->select('service_cases.*')
                      ->orderBy('users.name', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            $cases = $query->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $cases
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing cases for admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list service cases'
            ], 500);
        }
    }

    /**
     * Display the specified service case.
     */
    public function show($id)
    {
        try {
            $case = ServiceCase::with(['client.user', 'responses.technician.user', 'images', 'rating', 'acceptedTechnician.user'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing case for admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Service case not found'
            ], 404);
        }
    }

    /**
     * Update the status of a service case.
     */
    public function updateStatus(UpdateCaseStatusRequest $request, $id)
    {
        try {
            $case = ServiceCase::findOrFail($id);
            $case->status = $request->status;
            $case->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Case status updated successfully',
                'data' => $case
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating case status for admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update case status'
            ], 500);
        }
    }
}
