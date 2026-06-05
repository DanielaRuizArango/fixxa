<?php

use App\Models\Rating;

test('admin can list ratings', function () {
    $admin = adminUser();
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
        'comment' => 'Buen servicio.',
    ]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.data');
});

test('admin can delete a rating', function () {
    $admin = adminUser();
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $rating = Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 2,
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/ratings/{$rating->id}")
        ->assertOk();

    $this->assertDatabaseMissing('service_ratings', ['id' => $rating->id]);
});
