<?php

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
