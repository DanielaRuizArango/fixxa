<?php

use App\Http\Controllers\Api\ChatController;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('client can start a conversation with a technician for a case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/chat/start', [
            'service_case_id' => $case->id,
            'technician_id' => $technician->technician->id,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.service_case_id', $case->id)
        ->assertJsonPath('data.client_id', $client->client->id)
        ->assertJsonPath('data.technician_id', $technician->technician->id);

    $this->assertDatabaseHas('conversations', [
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);
});

test('technician cannot create a new conversation before client starts it', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/chat/start', [
            'service_case_id' => $case->id,
        ]);

    $response
        ->assertForbidden()
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseCount('conversations', 0);
});

test('conversation participants can send and read messages', function () {
    Event::fake([\App\Events\MessageSent::class]);

    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $conversation = Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $sendResponse = $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [
            'message' => 'Hola, podemos coordinar la visita?',
        ]);

    $sendResponse
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.message', 'Hola, podemos coordinar la visita?');

    $message = Message::first();
    expect($message->sender_id)->toBe($client->id);

    $showResponse = $this
        ->actingAs($technician, 'sanctum')
        ->getJson("/api/chat/{$conversation->id}");

    $showResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.messages');

    $this->assertDatabaseHas('messages', [
        'id' => $message->id,
        'is_read' => true,
    ]);
});

test('non participant cannot access a conversation', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $conversation = Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($otherClient, 'sanctum')
        ->getJson("/api/chat/{$conversation->id}");

    $response
        ->assertForbidden()
        ->assertJsonPath('status', 'error');
});

test('broadcast auth allows conversation participants on private chat channel', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $conversation = Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-chat.{$conversation->id}",
        ]);

    $response
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('broadcast auth denies users outside the private chat channel', function () {
    $client = clientUser();
    $otherTechnician = technicianUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $conversation = Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($otherTechnician, 'sanctum')
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-chat.{$conversation->id}",
        ]);

    $response->assertForbidden();
});

test('client can list their conversations', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/chat');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['meta' => ['total', 'current_page']]);
});

test('technician can list their conversations', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    Conversation::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/chat')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('client must provide technician id to start a conversation', function () {
    $client = clientUser();
    $case = serviceCaseFor($client);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/chat/start', [
            'service_case_id' => $case->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('starting an existing conversation does not duplicate it', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this->actingAs($client, 'sanctum')->postJson('/api/chat/start', [
        'service_case_id' => $case->id,
        'technician_id' => $technician->technician->id,
    ])->assertOk();

    $this->actingAs($client, 'sanctum')->postJson('/api/chat/start', [
        'service_case_id' => $case->id,
        'technician_id' => $technician->technician->id,
    ])->assertOk();

    $this->assertDatabaseCount('conversations', 1);
});

test('technician can open an existing conversation started by the client', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this->actingAs($client, 'sanctum')->postJson('/api/chat/start', [
        'service_case_id' => $case->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/chat/start', [
            'service_case_id' => $case->id,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('send message requires a message body', function () {
    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('non participant cannot send messages', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($otherClient, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [
            'message' => 'Mensaje no autorizado',
        ])
        ->assertForbidden();
});

test('sending a message notifies the recipient', function () {
    Event::fake([\App\Events\MessageSent::class]);

    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [
            'message' => 'Cuando puedes pasar?',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $technician->id,
        'notifiable_type' => \App\Models\User::class,
    ]);

    $notification = $technician->fresh()->notifications->first();
    expect($notification->data['type'])->toBe('new_message');
    expect($notification->data['sender_id'])->toBe($client->id);
});

test('guest cannot access chat endpoints', function () {
    $this->getJson('/api/chat')->assertUnauthorized();
    $this->postJson('/api/chat/start', [])->assertUnauthorized();
});

test('starting a conversation requires valid input', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/chat/start', [
            'service_case_id' => 999999,
            'technician_id' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('start conversation rejects users without client or technician role', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $admin = adminUser();

    $request = Request::create('/api/chat/start', 'POST', [
        'service_case_id' => $case->id,
        'technician_id' => $technician->technician->id,
    ]);
    Auth::setUser($admin);

    $response = app(ChatController::class)->startConversation($request);

    expect($response->getStatusCode())->toBe(403);
    expect($response->getData(true)['message'])->toBe('Unauthorized role.');
});

test('technician can send a message that notifies the client', function () {
    Event::fake([\App\Events\MessageSent::class]);

    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [
            'message' => 'Llego en 30 minutos.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.message', 'Llego en 30 minutos.');

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $client->id,
        'notifiable_type' => \App\Models\User::class,
    ]);
});

test('send message continues when notification delivery fails', function () {
    Event::fake([\App\Events\MessageSent::class]);
    Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, function () {
        throw new \Exception('Notification failed');
    });

    $client = clientUser();
    $technician = technicianUser();
    $conversation = Conversation::create([
        'service_case_id' => serviceCaseFor($client)->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/chat/{$conversation->id}/send", [
            'message' => 'Mensaje con fallo de notificacion',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'success');
});
