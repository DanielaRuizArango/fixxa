<?php

use App\Notifications\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;

test('user can list notifications with unread count', function () {
    $client = clientUser();
    $technician = technicianUser();

    $message = Message::create([
        'conversation_id' => Conversation::create([
            'service_case_id' => serviceCaseFor($client)->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
        ])->id,
        'sender_id' => $client->id,
        'message' => 'Hola',
        'is_read' => false,
    ]);

    $client->notify(new MessageReceived($message, $technician));
    $client->notify(new MessageReceived($message, $technician));

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/notifications');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.unread_count', 2)
        ->assertJsonCount(2, 'data.notifications');
});

test('user can mark all notifications as read', function () {
    $client = clientUser();
    $technician = technicianUser();
    $message = Message::create([
        'conversation_id' => Conversation::create([
            'service_case_id' => serviceCaseFor($client)->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
        ])->id,
        'sender_id' => $technician->id,
        'message' => 'Ok',
        'is_read' => false,
    ]);

    $client->notify(new MessageReceived($message, $technician));

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/notifications/mark-as-read')
        ->assertOk()
        ->assertJsonPath('message', 'All notifications marked as read');

    expect($client->fresh()->unreadNotifications)->toHaveCount(0);
});

test('user can mark a single notification as read', function () {
    $client = clientUser();
    $technician = technicianUser();
    $message = Message::create([
        'conversation_id' => Conversation::create([
            'service_case_id' => serviceCaseFor($client)->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
        ])->id,
        'sender_id' => $technician->id,
        'message' => 'Listo',
        'is_read' => false,
    ]);

    $client->notify(new MessageReceived($message, $technician));
    $notification = $client->fresh()->notifications->first();

    $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('message', 'Notification marked as read');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user can delete a notification', function () {
    $technician = technicianUser();
    $client = clientUser();
    $message = Message::create([
        'conversation_id' => Conversation::create([
            'service_case_id' => serviceCaseFor($client)->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
        ])->id,
        'sender_id' => $client->id,
        'message' => 'Borrar esto',
        'is_read' => false,
    ]);

    $technician->notify(new MessageReceived($message, $client));
    $notification = $technician->fresh()->notifications->first();

    $this
        ->actingAs($technician, 'sanctum')
        ->deleteJson("/api/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Notification deleted');

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

test('user cannot manage another users notifications', function () {
    $owner = clientUser();
    $intruder = clientUser();
    $technician = technicianUser();
    $message = Message::create([
        'conversation_id' => Conversation::create([
            'service_case_id' => serviceCaseFor($owner)->id,
            'client_id' => $owner->client->id,
            'technician_id' => $technician->technician->id,
        ])->id,
        'sender_id' => $technician->id,
        'message' => 'Privado',
        'is_read' => false,
    ]);

    $owner->notify(new MessageReceived($message, $technician));
    $notification = $owner->fresh()->notifications->first();

    $this
        ->actingAs($intruder, 'sanctum')
        ->patchJson("/api/notifications/{$notification->id}/read")
        ->assertNotFound();
});

test('guest cannot access notifications', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});
