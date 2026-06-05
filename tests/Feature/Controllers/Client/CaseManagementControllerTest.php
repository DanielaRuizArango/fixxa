<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Event;

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

test('client can cancel an active case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/cancel");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Caso cancelado.');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'cancelled',
    ]);
});

test('case management endpoints require a client profile', function () {
    $user = clientRoleWithoutProfile();

    $this->actingAs($user, 'sanctum')->postJson('/api/client/cases/1/proposals/1/accept')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');

    $this->actingAs($user, 'sanctum')->deleteJson('/api/client/cases/1/proposals/1/reject')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');

    $this->actingAs($user, 'sanctum')->patchJson('/api/client/cases/1/resolve')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');

    $this->actingAs($user, 'sanctum')->patchJson('/api/client/cases/1/cancel')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');
});

test('client cannot accept proposals on resolved cases', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'resolved']);
    $proposal = caseResponseFor($case, $technician);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/accept")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Solo puedes aceptar propuestas en casos activos o con respuestas.');
});

test('client cannot accept a proposal that does not belong to the case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $otherCase = serviceCaseFor($client, ['status' => 'responded']);
    $proposal = caseResponseFor($otherCase, $technician);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/accept")
        ->assertNotFound()
        ->assertJsonPath('message', 'La propuesta no existe o no pertenece a este caso.');
});

test('accepting a proposal still succeeds when initial message creation fails', function () {
    Event::listen('eloquent.creating: '.Message::class, function () {
        throw new \Exception('Message creation failed');
    });

    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician, [
        'questions' => 'Puedo revisarlo manana.',
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/accept")
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'pending',
        'accepted_technician_id' => $technician->technician->id,
    ]);
});

test('rejecting an accepted proposal reverts the case when other proposals remain', function () {
    $client = clientUser();
    $acceptedTechnician = technicianUser(['name' => 'Tecnico Aceptado']);
    $otherTechnician = technicianUser(['name' => 'Tecnico Alterno']);
    $case = serviceCaseFor($client, [
        'status' => 'pending',
        'accepted_technician_id' => $acceptedTechnician->technician->id,
    ]);
    $acceptedProposal = caseResponseFor($case, $acceptedTechnician);
    caseResponseFor($case, $otherTechnician);

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}/proposals/{$acceptedProposal->id}/reject")
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'responded',
        'accepted_technician_id' => null,
    ]);
});

test('reject proposal returns forbidden for another clients case', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($otherClient, ['status' => 'responded']);
    $proposal = caseResponseFor($case, $technician);

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}/proposals/{$proposal->id}/reject")
        ->assertForbidden()
        ->assertJsonPath('message', 'El caso no existe o no te pertenece.');
});

test('reject proposal returns not found for unknown proposal', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'responded']);

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}/proposals/99999/reject")
        ->assertNotFound()
        ->assertJsonPath('message', 'La propuesta no existe o no pertenece a este caso.');
});

test('client cannot resolve another clients case', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $case = serviceCaseFor($otherClient, ['status' => 'active']);

    $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/resolve")
        ->assertForbidden()
        ->assertJsonPath('message', 'El caso no existe o no te pertenece.');
});

test('client cannot resolve a cancelled case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'cancelled']);

    $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/resolve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Solo puedes resolver casos que están activos, pendientes o respondidos.');
});

test('client cannot cancel another clients case', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $case = serviceCaseFor($otherClient, ['status' => 'active']);

    $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'El caso no existe o no te pertenece.');
});

test('client cannot cancel a resolved case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->patchJson("/api/client/cases/{$case->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseHas('service_cases', [
        'id' => $case->id,
        'status' => 'resolved',
    ]);
});
