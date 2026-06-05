<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('client can create a service case', function () {
    Storage::fake('public');
    $client = clientUser();

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/cases', [
            'title' => 'Reparar lavadora',
            'description' => 'No centrifuga correctamente.',
            'service_type' => 'presential',
            'city' => 'Manizales',
            'images' => [UploadedFile::fake()->image('case.jpg')],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Reparar lavadora');

    $this->assertDatabaseHas('service_cases', [
        'client_id' => $client->client->id,
        'title' => 'Reparar lavadora',
        'status' => 'active',
    ]);
});

test('client can list their service cases', function () {
    $client = clientUser();
    serviceCaseFor($client, ['title' => 'Caso uno']);
    serviceCaseFor($client, ['title' => 'Caso dos']);

    $otherClient = clientUser();
    serviceCaseFor($otherClient, ['title' => 'Caso ajeno']);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.data');
});

test('client can view update and delete an active case', function () {
    Storage::fake('public');
    $client = clientUser();
    $case = serviceCaseFor($client, [
        'title' => 'Caso original',
        'city' => 'Manizales',
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson("/api/client/cases/{$case->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $case->id);

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Caso actualizado',
            'description' => 'Descripcion actualizada.',
            'service_type' => 'remote',
            'city' => 'Manizales',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Caso actualizado');

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}")
        ->assertOk();

    $this->assertSoftDeleted('service_cases', ['id' => $case->id]);
});

test('client cannot modify a resolved case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Intento de cambio',
            'description' => 'No deberia permitirse.',
            'service_type' => 'presential',
            'city' => 'Manizales',
        ])
        ->assertForbidden();
});

test('client cannot access another clients case', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $case = serviceCaseFor($otherClient);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson("/api/client/cases/{$case->id}")
        ->assertNotFound();
});

test('client can filter service cases by status and search', function () {
    $client = clientUser();
    serviceCaseFor($client, ['title' => 'Lavadora rota', 'status' => 'active']);
    serviceCaseFor($client, ['title' => 'Nevera dañada', 'status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.status', 'active');

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?search=Nevera')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.title', 'Nevera dañada');
});

test('creating a service case requires mandatory fields', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/cases', [])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseCount('service_cases', 0);
});
