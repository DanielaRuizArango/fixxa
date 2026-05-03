<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
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
            $query = ServiceCase::with(['client.user', 'responses.technician.user', 'rating', 'acceptedTechnician.user']);

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

            $cases = $query->orderBy('created_at', 'desc')->paginate(10);

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
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,pending,responded,resolved,cancelled'
        ]);

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
