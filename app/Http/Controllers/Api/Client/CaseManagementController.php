<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\CaseResponse;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

class CaseManagementController extends Controller
{
    /**
     * Accept a technician's proposal for a service case.
     * Sets the case status to 'pending' and records the accepted technician.
     */
    public function acceptProposal(Request $request, $caseId, $responseId)
    {
        $client = $request->user()->client;

        if (!$client) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes un perfil de cliente asociado.',
            ], 403);
        }

        $serviceCase = ServiceCase::where('client_id', $client->id)->find($caseId);

        if (!$serviceCase) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El caso no existe o no te pertenece.',
            ], 403);
        }

        if (!in_array($serviceCase->status, ['active', 'responded'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Solo puedes aceptar propuestas en casos activos o con respuestas.',
            ], 422);
        }

        $response = CaseResponse::where('service_case_id', $serviceCase->id)->find($responseId);

        if (!$response) {
            return response()->json([
                'status'  => 'error',
                'message' => 'La propuesta no existe o no pertenece a este caso.',
            ], 404);
        }

        $serviceCase->update([
            'status'                => 'pending',
            'accepted_technician_id' => $response->technician_id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Propuesta aceptada.',
            'data'    => $serviceCase->load(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user']),
        ]);
    }

    /**
     * Reject a technician's proposal for a service case.
     * Removes the response; if no more responses remain, resets the case to 'active'.
     */
    public function rejectProposal(Request $request, $caseId, $responseId)
    {
        $client = $request->user()->client;

        if (!$client) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes un perfil de cliente asociado.',
            ], 403);
        }

        $serviceCase = ServiceCase::where('client_id', $client->id)->find($caseId);

        if (!$serviceCase) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El caso no existe o no te pertenece.',
            ], 403);
        }

        $response = CaseResponse::where('service_case_id', $serviceCase->id)->find($responseId);

        if (!$response) {
            return response()->json([
                'status'  => 'error',
                'message' => 'La propuesta no existe o no pertenece a este caso.',
            ], 404);
        }

        $response->delete();

        // If no more responses remain, revert the case to 'active'
        $remainingResponses = CaseResponse::where('service_case_id', $serviceCase->id)->count();
        if ($remainingResponses === 0) {
            $serviceCase->update(['status' => 'active']);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Propuesta rechazada.',
        ]);
    }

    /**
     * Mark a service case as resolved.
     * Only allowed when the case is in 'pending' status.
     */
    public function resolveCase(Request $request, $caseId)
    {
        $client = $request->user()->client;

        if (!$client) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes un perfil de cliente asociado.',
            ], 403);
        }

        $serviceCase = ServiceCase::where('client_id', $client->id)->find($caseId);

        if (!$serviceCase) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El caso no existe o no te pertenece.',
            ], 403);
        }

        if ($serviceCase->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Solo puedes resolver casos en estado pendiente.',
            ], 422);
        }

        $serviceCase->update(['status' => 'resolved']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Caso marcado como resuelto.',
            'data'    => $serviceCase->load(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user']),
        ]);
    }
}
