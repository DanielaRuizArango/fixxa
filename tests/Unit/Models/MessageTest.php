<?php

use App\Models\Conversation;
use App\Models\Message;

test('message belongs to conversation and sender', function () {
    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $technician->id,
        'message' => 'Confirmado',
        'is_read' => true,
    ]);

    $loaded = Message::with(['conversation', 'sender'])->find($message->id);

    expect($loaded->conversation->id)->toBe($conversation->id);
    expect($loaded->sender->id)->toBe($technician->id);
    expect((bool) $loaded->is_read)->toBeTrue();
});
