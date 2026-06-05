<?php

use App\Models\Rating;
use Illuminate\Support\Carbon;

test('admin can fetch system alerts', function () {
    $admin = adminUser();
    $client = clientUser();

    $unansweredCase = serviceCaseFor($client, [
        'title' => 'Caso sin respuesta',
        'status' => 'active',
    ]);
    $unansweredCase->forceFill([
        'created_at' => Carbon::now()->subHours(30),
        'updated_at' => Carbon::now()->subHours(30),
    ])->save();

    $technician = technicianUser();
    foreach ([2, 2, 2] as $score) {
        $case = serviceCaseFor($client, [
            'status' => 'resolved',
            'accepted_technician_id' => $technician->technician->id,
        ]);

        Rating::create([
            'service_case_id' => $case->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->technician->id,
            'score' => $score,
        ]);
    }

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/alerts');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'alerts' => ['unanswered_cases', 'poor_technicians'],
                'summary' => ['total_critical_alerts'],
            ],
        ])
        ->assertJsonCount(1, 'data.alerts.unanswered_cases')
        ->assertJsonCount(1, 'data.alerts.poor_technicians');
});

test('guest cannot fetch system alerts', function () {
    $this->getJson('/api/admin/alerts')->assertUnauthorized();
});
