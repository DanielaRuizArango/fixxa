<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Display a listing of conversations for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $conversations = [];

        if ($user->hasRole('client')) {
            $conversations = Conversation::where('client_id', $user->client->id)
                ->with(['technician.user', 'serviceCase', 'messages' => function($q) {
                    $q->latest()->limit(1);
                }])
                ->paginate(10);
        } elseif ($user->hasRole('technician')) {
            $conversations = Conversation::where('technician_id', $user->technician->id)
                ->with(['client.user', 'serviceCase', 'messages' => function($q) {
                    $q->latest()->limit(1);
                }])
                ->paginate(10);
        }

        return $this->successResponse($conversations);
    }

    /**
     * Start or fetch a conversation for a specific case and technician.
     */
    public function startConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_case_id' => 'required|exists:service_cases,id',
            'technician_id' => 'nullable|exists:technicians,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors());
        }

        $user = Auth::user();
        $serviceCase = ServiceCase::findOrFail($request->service_case_id);
        
        $clientId = null;
        $technicianId = null;

        if ($user->hasRole('client')) {
            $clientId = $user->client->id;
            $technicianId = $request->technician_id;
            if (!$technicianId) {
                return $this->errorResponse('Technician ID is required for clients.', 422);
            }
        } elseif ($user->hasRole('technician')) {
            $technicianId = $user->technician->id;
            $clientId = $serviceCase->client_id;
        } else {
             return $this->errorResponse('Unauthorized role.', 403);
        }

        // Buscar si ya existe la conversación
        $conversation = Conversation::where([
            'service_case_id' => $serviceCase->id,
            'client_id' => $clientId,
            'technician_id' => $technicianId,
        ])->first();

        if (!$conversation) {
            // Solo el cliente puede iniciar una nueva conversación
            if (!$user->hasRole('client')) {
                return $this->errorResponse('El chat no ha sido iniciado por el cliente. Solo el cliente puede iniciar la conversación.', 403);
            }

            // Crear la conversación
            $conversation = Conversation::create([
                'service_case_id' => $serviceCase->id,
                'client_id' => $clientId,
                'technician_id' => $technicianId,
            ]);
        }

        return $this->successResponse($conversation->load(['technician.user', 'client.user', 'serviceCase']));
    }

    /**
     * Display messages for a specific conversation.
     */
    public function show($id)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($id);

        // Check if user belongs to conversation
        if (($user->hasRole('client') && $conversation->client_id !== $user->client->id) ||
            ($user->hasRole('technician') && $conversation->technician_id !== $user->technician->id)) {
            return $this->errorResponse('Unauthorized Access', 403);
        }

        // Mark messages as read
        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', $user->id)
            ->update(['is_read' => true]);

        return $this->successResponse([
            'conversation' => $conversation->load(['client.user', 'technician.user', 'serviceCase']),
            'messages' => $conversation->messages()->oldest()->get()
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors());
        }

        $user = Auth::user();
        $conversation = Conversation::findOrFail($id);

        // Check if user belongs to conversation
        if (($user->hasRole('client') && $conversation->client_id !== $user->client->id) ||
            ($user->hasRole('technician') && $conversation->technician_id !== $user->technician->id)) {
            return $this->errorResponse('Unauthorized Access', 403);
        }

        $message = Message::create([
            'conversation_id' => $id,
            'sender_id' => $user->id,
            'message' => $request->message,
        ]);

        // Notificar al destinatario
        $recipient = null;
        if ($user->hasRole('client')) {
            $recipient = $conversation->technician->user;
        } else {
            $recipient = $conversation->client->user;
        }

        if ($recipient) {
            try {
                $recipient->notify(new \App\Notifications\MessageReceived($message, $user));
            } catch (\Exception $e) {
                Log::error('Error enviando notificación de mensaje: ' . $e->getMessage());
            }
        }

        broadcast(new \App\Events\MessageSent($message))->toOthers();

        return $this->successResponse($message, null, 201);
    }
}
