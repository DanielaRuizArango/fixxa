<?php

use App\Models\CaseResponse;
use Illuminate\Support\Facades\Event;

test('technician can create a proposal for an active case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 95000,
            'questions' => 'Puedo asistir manana.',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.technician_id', $technician->technician->id);

    $this->assertDatabaseHas('case_responses', [
        'service_case_id' => $case->id,
        'technician_id' => $technician->technician->id,
        'estimated_cost' => 95000,
    ]);

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'responded',
    ]);
});

test('client role cannot create technician proposals', function () {
    $client = clientUser();
    $case = serviceCaseFor($client);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 95000,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('case_responses', 0);
});

test('technician can update their proposal before it is accepted', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician, ['estimated_cost' => 100000]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$proposal->id}", [
            'estimated_cost' => 80000,
            'questions' => 'Nuevo valor con descuento.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.estimated_cost', 80000);

    $this->assertDatabaseHas('case_responses', [
        'id' => $proposal->id,
        'estimated_cost' => 80000,
        'questions' => 'Nuevo valor con descuento.',
    ]);
});

test('technician cannot update an accepted proposal', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);
    $proposal = caseResponseFor($case, $technician, ['estimated_cost' => 100000]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$proposal->id}", [
            'estimated_cost' => 80000,
            'questions' => 'Intento de cambio.',
        ]);

    $response
        ->assertForbidden()
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseHas('case_responses', [
        'id' => $proposal->id,
        'estimated_cost' => 100000,
    ]);
});

test('my responses endpoint returns only authenticated technicians proposals', function () {
    $client = clientUser();
    $technician = technicianUser();
    $otherTechnician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $ownProposal = caseResponseFor($case, $technician);
    caseResponseFor($case, $otherTechnician);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-responses');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $ownProposal->id);
});

test('technician can list available cases', function () {
    $client = clientUser();
    $technician = technicianUser();
    $activeCase = serviceCaseFor($client, ['title' => 'Caso disponible', 'status' => 'active']);
    $resolvedCase = serviceCaseFor($client, ['title' => 'Caso resuelto', 'status' => 'resolved']);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.data.0.id', $activeCase->id);

    $ids = collect($response->json('data.data'))->pluck('id');
    expect($ids)->not->toContain($resolvedCase->id);
});

test('technician can filter available cases by search and service type', function () {
    $client = clientUser();
    $technician = technicianUser();
    serviceCaseFor($client, ['title' => 'Instalar aire', 'service_type' => 'remote', 'status' => 'active']);
    serviceCaseFor($client, ['title' => 'Reparar puerta', 'service_type' => 'presential', 'status' => 'active']);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases?search=aire&service_type=remote')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.title', 'Instalar aire');
});

test('technician can view a case detail', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson("/api/technician/cases/{$case->id}")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $case->id)
        ->assertJsonPath('data.title', $case->title);
});

test('store proposal requires a technician profile', function () {
    $technician = technicianRoleWithoutProfile();
    $case = serviceCaseFor(clientUser(), ['status' => 'active']);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 95000,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de técnico asociado.');
});

test('store proposal returns 500 on internal error', function () {
    $technician = technicianUser();
    $case = serviceCaseFor(clientUser(), ['status' => 'active']);

    Event::listen('eloquent.creating: '.CaseResponse::class, function () {
        Event::forget('eloquent.creating: '.CaseResponse::class);
        throw new \Exception('Create failed');
    });

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 95000,
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Error al enviar la respuesta.');
});

test('store proposal on responded case keeps case status unchanged', function () {
    $client = clientUser();
    $firstTechnician = technicianUser();
    $secondTechnician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $this
        ->actingAs($firstTechnician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 95000,
            'questions' => 'Primera propuesta.',
        ])
        ->assertCreated();

    $this
        ->actingAs($secondTechnician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 88000,
            'questions' => 'Segunda propuesta.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'responded',
    ]);

    $this->assertDatabaseCount('case_responses', 2);
});

test('update proposal requires a technician profile', function () {
    $technician = technicianRoleWithoutProfile();
    $case = serviceCaseFor(clientUser(), ['status' => 'responded']);
    $proposal = caseResponseFor($case, technicianUser());

    $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$proposal->id}", [
            'estimated_cost' => 80000,
        ])
        ->assertForbidden();
});

test('technician cannot update proposal on resolved or cancelled case', function () {
    $client = clientUser();
    $technician = technicianUser();

    foreach (['resolved', 'cancelled'] as $status) {
        $case = serviceCaseFor($client, ['status' => $status]);
        $proposal = caseResponseFor($case, $technician);

        $this
            ->actingAs($technician, 'sanctum')
            ->putJson("/api/technician/responses/{$proposal->id}", [
                'estimated_cost' => 80000,
            ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }
});

test('technician cannot update a non existent or foreign proposal', function () {
    $technician = technicianUser();
    $case = serviceCaseFor(clientUser(), ['status' => 'responded']);
    $foreignProposal = caseResponseFor($case, technicianUser());

    $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$foreignProposal->id}", [
            'estimated_cost' => 80000,
        ])
        ->assertStatus(500);

    $this
        ->actingAs($technician, 'sanctum')
        ->putJson('/api/technician/responses/99999', [
            'estimated_cost' => 80000,
        ])
        ->assertStatus(500);
});

test('update proposal validation rejects invalid estimated cost', function () {
    $technician = technicianUser();
    $case = serviceCaseFor(clientUser(), ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician);

    $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$proposal->id}", [
            'estimated_cost' => -10,
        ])
        ->assertStatus(500);
});

test('update proposal returns 500 on internal error', function () {
    $technician = technicianUser();
    $case = serviceCaseFor(clientUser(), ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician);

    Event::listen('eloquent.updating: '.CaseResponse::class, function () {
        Event::forget('eloquent.updating: '.CaseResponse::class);
        throw new \Exception('Update failed');
    });

    $this
        ->actingAs($technician, 'sanctum')
        ->putJson("/api/technician/responses/{$proposal->id}", [
            'estimated_cost' => 80000,
        ])
        ->assertStatus(500);
});

test('technician can filter available cases by city', function () {
    $client = clientUser();
    $technician = technicianUser();

    serviceCaseFor($client, [
        'title' => 'Caso Bogota',
        'city' => 'Bogota',
        'status' => 'active',
        'latitude' => 4.7110,
        'longitude' => -74.0721,
    ]);

    serviceCaseFor($client, [
        'title' => 'Caso Manizales',
        'city' => 'Manizales',
        'status' => 'active',
        'latitude' => 5.0689,
        'longitude' => -75.5174,
    ]);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases?city=Bogota')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.city', 'Bogota');
});

test('technician can sort available cases by responses count client name and city', function () {
    $technician = technicianUser();
    $clientA = clientUser(['name' => 'Ana Cliente']);
    $clientB = clientUser(['name' => 'Zoe Cliente']);

    $caseWithResponses = serviceCaseFor($clientA, [
        'title' => 'Caso con respuestas',
        'city' => 'Armenia',
        'status' => 'active',
    ]);
    serviceCaseFor($clientB, [
        'title' => 'Caso sin respuestas',
        'city' => 'Bogota',
        'status' => 'active',
    ]);

    caseResponseFor($caseWithResponses, $technician);
    caseResponseFor($caseWithResponses, technicianUser());

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases?sort_by=responses_count&sort_order=desc')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $caseWithResponses->id);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases?sort_by=client_name&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('data.data.0.client_id', $clientA->client->id);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases?sort_by=city&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('data.data.0.city', 'Armenia');
});

test('technician can filter my responses by search and status', function () {
    $client = clientUser();
    $technician = technicianUser();

    $respondedCase = serviceCaseFor($client, [
        'title' => 'Caso filtrado',
        'status' => 'responded',
    ]);
    $activeCase = serviceCaseFor($client, [
        'title' => 'Otro caso',
        'status' => 'active',
    ]);

    $matchingProposal = caseResponseFor($respondedCase, $technician);
    caseResponseFor($activeCase, $technician);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-responses?search=filtrado')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $matchingProposal->id);

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/my-responses?status=responded')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $matchingProposal->id);
});

test('show case returns 404 for invalid id', function () {
    $technician = technicianUser();

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/cases/99999')
        ->assertNotFound();
});
