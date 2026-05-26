<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\CaseResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\AuditLogger;

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

        $response = CaseResponse::where('service_case_id', $serviceCase->id)
            ->with('technician')
            ->find($responseId);

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

        // Create or get the conversation between client and technician
        $conversation = Conversation::firstOrCreate(
            [
                'service_case_id' => $serviceCase->id,
                'client_id'       => $client->id,
                'technician_id'   => $response->technician_id,
            ]
        );

        // Create the initial message with the technician's response content
        if ($conversation->messages()->count() === 0 && $response->questions) {
            try {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $response->technician->user_id,
                    'message'         => $response->questions,
                    'is_read'         => false,
                ]);
            } catch (\Exception $e) {
                Log::error('Error creating initial message: ' . $e->getMessage());
            }
        }

        // Log action for administrators
        AuditLogger::log(
            'accept_proposal',
            'App\\Models\\ServiceCase',
            $serviceCase->id,
            "El cliente {$request->user()->name} aceptó la propuesta del técnico en el caso #{$serviceCase->id}."
        );

        return response()->json([
            'status'      => 'success',
            'message'     => 'Propuesta aceptada.',
            'conversation_id' => $conversation->id,
            'messages'    => $conversation->messages()->with('sender')->oldest()->get(),
            'data'        => $serviceCase->load(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user']),
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

        $wasAccepted = $serviceCase->accepted_technician_id === $response->technician_id;

        $response->delete();

        // If no more responses remain, revert the case to 'active'
        $remainingResponses = CaseResponse::where('service_case_id', $serviceCase->id)->count();
        if ($remainingResponses === 0) {
            $serviceCase->update([
                'status' => 'active',
                'accepted_technician_id' => null
            ]);
        } elseif ($wasAccepted) {
            // Revert back to responded if there are other proposals
            $serviceCase->update([
                'status' => 'responded',
                'accepted_technician_id' => null
            ]);
        }

        // Log action for administrators
        AuditLogger::log(
            'reject_proposal',
            'App\\Models\\ServiceCase',
            $serviceCase->id,
            "El cliente {$request->user()->name} rechazó una propuesta en el caso #{$serviceCase->id}."
        );

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

        // Log action for administrators
        AuditLogger::log(
            'resolve_case',
            'App\\Models\\ServiceCase',
            $serviceCase->id,
            "El cliente {$request->user()->name} marcó el caso #{$serviceCase->id} como resuelto."
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Caso marcado como resuelto.',
            'data'    => $serviceCase->load(['images', 'responses.technician.user', 'rating', 'acceptedTechnician.user']),
        ]);
    }

    /**
     * Cancel a service case.
     * Allowed when the case is in 'active', 'pending', or 'responded' status.
     */
    public function cancelCase(Request $request, $caseId)
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

        if (!in_array($serviceCase->status, ['active', 'pending', 'responded'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No puedes cancelar un caso que ya está resuelto o cancelado.',
            ], 422);
        }

        $serviceCase->update(['status' => 'cancelled']);

        AuditLogger::log(
            'cancel_case',
            'App\\Models\\ServiceCase',
            $serviceCase->id,
            "El cliente {$request->user()->name} canceló el caso #{$serviceCase->id}."
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Caso cancelado.',
        ]);
    }
}
