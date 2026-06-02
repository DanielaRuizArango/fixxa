<?php

use App\Models\Rating;

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
