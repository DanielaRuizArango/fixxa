<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;

test('message sent event broadcasts on private chat channel', function () {
    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $client->id,
        'message' => 'Mensaje de prueba',
        'is_read' => false,
    ]);

    $event = new MessageSent($message);

    expect($event->message->id)->toBe($message->id);
    expect($event->broadcastAs())->toBe('message.sent');

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-chat.' . $conversation->id);
});
