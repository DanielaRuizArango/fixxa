<?php

use App\Models\Rating;
use Illuminate\Support\Facades\Event;

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

test('admin can filter ratings by search and score', function () {
    $admin = adminUser();
    $client = clientUser(['name' => 'Cliente Rating']);
    $technician = technicianUser(['name' => 'Tecnico Rating']);

    $case = serviceCaseFor($client, [
        'title' => 'Caso calificacion especial',
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
        'comment' => 'Muy bueno.',
    ]);

    $otherClient = clientUser();
    $otherCase = serviceCaseFor($otherClient, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    Rating::create([
        'service_case_id' => $otherCase->id,
        'client_id' => $otherClient->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 2,
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings?search=Cliente Rating')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.score', 5);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings?search=Caso calificacion especial')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings?search=Tecnico Rating')
        ->assertOk()
        ->assertJsonCount(2, 'data.data');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings?score=2')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.score', 2);
});

test('client cannot access admin ratings', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/admin/ratings')
        ->assertForbidden();
});

test('guest cannot access admin ratings', function () {
    $this->getJson('/api/admin/ratings')->assertUnauthorized();
});

test('admin index returns 500 when listing fails', function () {
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
    ]);

    Event::listen('eloquent.retrieved: '.Rating::class, function () {
        Event::forget('eloquent.retrieved: '.Rating::class);
        throw new \Exception('Listing failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ratings')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to list ratings');
});

test('admin destroy returns 500 when rating is not found', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson('/api/admin/ratings/99999')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to delete rating');
});
