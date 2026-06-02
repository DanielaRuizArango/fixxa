<?php

use App\Models\Conversation;

test('client can accept a proposal and a conversation is created', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician, [
        'questions' => 'Incluye diagnostico inicial.',
    ]);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/accept");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.accepted_technician_id', $technician->technician->id)
        ->assertJsonStructure(['conversation_id', 'messages']);

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this->assertDatabaseHas('conversations', [
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
    ]);

    expect(Conversation::first()->messages)->toHaveCount(1);
});

test('client cannot accept a proposal for another clients case', function () {
    $owner = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($owner, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician);

    $response = $this
        ->actingAs($otherClient, 'sanctum')
        ->postJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/accept");

    $response
        ->assertForbidden()
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'responded',
        'accepted_technician_id' => null,
    ]);
});

test('rejecting the last proposal returns the case to active', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/reject");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertSoftDeleted('case_responses', ['id' => $proposal->id]);
    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'active',
        'accepted_technician_id' => null,
    ]);
});

test('client can resolve their own active case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/resolve");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'resolved');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'resolved',
    ]);
});
