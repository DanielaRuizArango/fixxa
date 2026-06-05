<?php

use App\Models\Rating;
use Illuminate\Support\Facades\Event;

test('client can rate a resolved case with accepted technician', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 5,
            'comment' => 'Excelente servicio.',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.score', 5)
        ->assertJsonPath('data.technician_id', $technician->technician->id);

    $this->assertDatabaseHas('service_ratings', [
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
    ]);
});

test('client cannot rate unresolved case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 4,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseCount('service_ratings', 0);
});

test('client cannot rate the same case twice', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 4,
        'comment' => 'Primera calificacion.',
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 5,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseCount('service_ratings', 1);
});

test('ratings endpoints require a client profile', function () {
    $user = clientRoleWithoutProfile();

    $this->actingAs($user, 'sanctum')->getJson('/api/client/ratings')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');
});

test('store rating requires a client profile', function () {
    $user = clientRoleWithoutProfile();
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/client/ratings', [
        'service_case_id' => $case->id,
        'score' => 5,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');
});

test('client cannot rate a case that does not belong to them', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($otherClient, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 4,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'El caso no existe o no te pertenece.');
});

test('client cannot rate a resolved case without assigned technician', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => null,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'El caso no tiene un técnico asignado.');
});

test('rating submission continues when technician notification fails', function () {
    Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, function () {
        throw new \Exception('Notification failed');
    });

    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 5,
            'comment' => 'Buen trabajo.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('service_ratings', [
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'score' => 5,
    ]);
});

test('ratings index only includes authenticated clients ratings', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);
    $otherCase = serviceCaseFor($otherClient, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $ownRating = Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
    ]);

    Rating::create([
        'service_case_id' => $otherCase->id,
        'client_id' => $otherClient->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 3,
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/ratings');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $ownRating->id);
});
