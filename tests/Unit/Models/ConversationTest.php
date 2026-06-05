<?php

use App\Models\Conversation;
use App\Models\Message;

test('conversation links case client technician and messages', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);

    $conversation = Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $client->id,
        'message' => 'Hola',
        'is_read' => false,
    ]);

    $loaded = Conversation::with(['serviceCase', 'client.user', 'technician.user', 'messages'])
        ->find($conversation->id);

    expect($loaded->serviceCase->id)->toBe($case->id);
    expect($loaded->client->user->id)->toBe($client->id);
    expect($loaded->technician->user->id)->toBe($technician->id);
    expect($loaded->messages)->toHaveCount(1);
});
