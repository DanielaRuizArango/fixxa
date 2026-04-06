<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    if ($user->role === 'client') {
        return $user->client->id === $conversation->client_id;
    }

    if ($user->role === 'technician') {
        return $user->technician->id === $conversation->technician_id;
    }

    return false;
});
