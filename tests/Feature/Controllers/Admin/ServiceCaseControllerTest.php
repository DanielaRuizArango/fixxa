<?php

use App\Models\ServiceCase;
use Illuminate\Support\Facades\Event;

test('admin can list and show service cases', function () {
    $admin = adminUser();
    $client = clientUser();
    $case = serviceCaseFor($client, ['title' => 'Caso admin visible']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.data.0.id', $case->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/cases/{$case->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $case->id);
});

test('admin can update case status', function () {
    $admin = adminUser();
    $case = serviceCaseFor(clientUser(), ['status' => 'active']);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/cases/{$case->id}/status", [
            'status' => 'cancelled',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'cancelled',
    ]);
});

test('admin case status update requires valid status', function () {
    $admin = adminUser();
    $case = serviceCaseFor(clientUser());

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/cases/{$case->id}/status", [
            'status' => 'invalid',
        ])
        ->assertStatus(422);
});

test('admin can filter service cases by search status city and service type', function () {
    $admin = adminUser();
    $client = clientUser();

    serviceCaseFor($client, [
        'title' => 'Reparacion laptops',
        'description' => 'Pantalla rota',
        'status' => 'active',
        'city' => 'Manizales',
        'service_type' => 'presential',
    ]);

    serviceCaseFor(clientUser(), [
        'title' => 'Otro caso',
        'description' => 'Instalacion remota',
        'status' => 'resolved',
        'city' => 'Bogota',
        'service_type' => 'remote',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?search=Reparacion')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.title', 'Reparacion laptops');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?status=active')
        ->assertOk()
        ->assertJsonPath('data.data.0.status', 'active');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?city=Manizales')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.city', 'Manizales');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?service_type=presential')
        ->assertOk()
        ->assertJsonPath('data.data.0.service_type', 'presential');
});

test('admin can sort service cases by responses count client name and technician name', function () {
    $admin = adminUser();
    $clientA = clientUser(['name' => 'Ana Cliente']);
    $clientB = clientUser(['name' => 'Zoe Cliente']);
    $technicianA = technicianUser(['name' => 'Alberto Tecnico']);
    $technicianB = technicianUser(['name' => 'Zara Tecnico']);

    $caseWithResponses = serviceCaseFor($clientA, [
        'title' => 'Caso con respuestas',
        'accepted_technician_id' => $technicianA->technician->id,
    ]);
    $caseWithoutResponses = serviceCaseFor($clientB, [
        'title' => 'Caso sin respuestas',
        'accepted_technician_id' => $technicianB->technician->id,
    ]);

    caseResponseFor($caseWithResponses, $technicianA);
    caseResponseFor($caseWithResponses, $technicianB);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?sort_by=responses_count&sort_order=desc')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $caseWithResponses->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?sort_by=client_name&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('data.data.0.client_id', $clientA->client->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases?sort_by=technician_name&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('data.data.0.accepted_technician_id', $technicianA->technician->id);
});

test('admin gets 404 when showing a non existent case', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases/99999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Service case not found');
});

test('guest cannot access admin service cases', function () {
    $this->getJson('/api/admin/cases')->assertUnauthorized();
});

test('admin index returns 500 when listing fails', function () {
    $admin = adminUser();
    serviceCaseFor(clientUser());

    Event::listen('eloquent.retrieved: '.ServiceCase::class, function () {
        Event::forget('eloquent.retrieved: '.ServiceCase::class);
        throw new \Exception('Listing failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/cases')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to list service cases');
});

test('admin update status returns 500 when case is not found', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson('/api/admin/cases/99999/status', [
            'status' => 'cancelled',
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to update case status');
});
