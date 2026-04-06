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

        if ($user->role === 'client') {
            $conversations = Conversation::where('client_id', $user->client->id)
                ->with(['technician.user', 'serviceCase', 'messages' => function($q) {
                    $q->latest()->limit(1);
                }])
                ->get();
        } elseif ($user->role === 'technician') {
            $conversations = Conversation::where('technician_id', $user->technician->id)
                ->with(['client.user', 'serviceCase', 'messages' => function($q) {
                    $q->latest()->limit(1);
                }])
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $conversations
        ]);
    }

    /**
     * Start or fetch a conversation for a specific case and technician.
     */
    public function startConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_case_id' => 'required|exists:service_cases,id',
            'technician_id' => 'required|exists:technicians,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if ($user->role !== 'client') {
            return response()->json(['status' => 'error', 'message' => 'Only clients can start a conversation.'], 403);
        }

        $conversation = Conversation::firstOrCreate([
            'service_case_id' => $request->service_case_id,
            'client_id' => $user->client->id,
            'technician_id' => $request->technician_id,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $conversation->load(['technician.user', 'serviceCase'])
        ]);
    }

    /**
     * Display messages for a specific conversation.
     */
    public function show($id)
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($id);

        // Check if user belongs to conversation
        if (($user->role === 'client' && $conversation->client_id !== $user->client->id) ||
            ($user->role === 'technician' && $conversation->technician_id !== $user->technician->id)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized Access'], 403);
        }

        // Mark messages as read
        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', $user->id)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation' => $conversation->load(['client.user', 'technician.user', 'serviceCase']),
                'messages' => $conversation->messages()->oldest()->get()
            ]
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $conversation = Conversation::findOrFail($id);

        // Check if user belongs to conversation
        if (($user->role === 'client' && $conversation->client_id !== $user->client->id) ||
            ($user->role === 'technician' && $conversation->technician_id !== $user->technician->id)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized Access'], 403);
        }

        $message = Message::create([
            'conversation_id' => $id,
            'sender_id' => $user->id,
            'message' => $request->message,
        ]);

        broadcast(new \App\Events\MessageSent($message))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);
    }
}
