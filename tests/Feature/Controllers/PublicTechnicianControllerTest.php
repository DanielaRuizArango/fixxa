<?php

use App\Models\Rating;
use App\Models\TechnicianAsset;

test('authenticated client can view a technician public profile', function () {
    $client = clientUser();
    $technicianUser = technicianUser([
        'name' => 'Juan Tecnico',
        'city' => 'Manizales',
    ]);
    $technician = $technicianUser->technician;

    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->id,
    ]);

    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->id,
        'score' => 5,
        'comment' => 'Excelente trabajo.',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/multimetro.jpg',
        'description' => 'Multimetro digital',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson("/api/technicians/{$technician->id}/profile");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Juan Tecnico')
        ->assertJsonPath('data.city', 'Manizales')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'experience',
                'title',
                'average_rating',
                'ratings',
                'assets',
            ],
        ])
        ->assertJsonCount(1, 'data.ratings')
        ->assertJsonCount(1, 'data.assets');
});

test('technician can also view another technicians public profile', function () {
    $viewer = technicianUser();
    $target = technicianUser(['name' => 'Perfil Publico']);

    $this
        ->actingAs($viewer, 'sanctum')
        ->getJson("/api/technicians/{$target->technician->id}/profile")
        ->assertOk()
        ->assertJsonPath('data.name', 'Perfil Publico');
});

test('guest cannot view technician public profile', function () {
    $technician = technicianUser()->technician;

    $this
        ->getJson("/api/technicians/{$technician->id}/profile")
        ->assertUnauthorized();
});

test('returns 404 for non existent technician profile', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/technicians/99999/profile')
        ->assertNotFound();
});
