<?php

use App\Models\Conversation;
use App\Models\Message;
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
