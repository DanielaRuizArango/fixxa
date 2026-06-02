<?php

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
