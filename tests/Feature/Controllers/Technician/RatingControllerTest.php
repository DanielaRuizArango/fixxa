<?php

use App\Models\Rating;

test('technician can view their ratings summary', function () {
    $technician = technicianUser();
    $client = clientUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
        'comment' => 'Muy buen trabajo.',
    ]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-rating');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total_ratings', 1)
        ->assertJsonPath('data.average_score', 5)
        ->assertJsonCount(1, 'data.ratings.data');
});

test('technician ratings can be filtered by score', function () {
    $technician = technicianUser();
    $client = clientUser();

    foreach ([3, 5] as $score) {
        $case = serviceCaseFor($client, [
            'status' => 'resolved',
            'accepted_technician_id' => $technician->technician->id,
        ]);

        Rating::create([
            'service_case_id' => $case->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
            'score' => $score,
        ]);
    }

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-rating?score=5');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.ratings.data')
        ->assertJsonPath('data.ratings.data.0.score', 5);
});

test('client cannot access technician ratings endpoint', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/technician/my-rating')
        ->assertForbidden();
});

test('ratings index requires a technician profile', function () {
    $technician = technicianRoleWithoutProfile();

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-rating')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de técnico asociado.');
});

test('technician ratings can be filtered by search', function () {
    $technician = technicianUser();
    $client = clientUser(['name' => 'Cliente Busqueda']);
    $otherClient = clientUser(['name' => 'Otro Cliente']);

    foreach ([$client, $otherClient] as $currentClient) {
        $case = serviceCaseFor($currentClient, [
            'title' => $currentClient->id === $client->id ? 'Caso especial busqueda' : 'Otro caso',
            'status' => 'resolved',
            'accepted_technician_id' => $technician->technician->id,
        ]);

        Rating::create([
            'service_case_id' => $case->id,
            'client_id' => $currentClient->client->id,
            'technician_id' => $technician->technician->id,
            'score' => 4,
        ]);
    }

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-rating?search=Cliente Busqueda')
        ->assertOk()
        ->assertJsonCount(1, 'data.ratings.data');

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-rating?search=Caso especial busqueda')
        ->assertOk()
        ->assertJsonCount(1, 'data.ratings.data');
});
