<?php

use App\Models\Rating;
use App\Models\ServiceCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

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

test('admin alerts returns empty summary when there are no critical alerts', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/alerts')
        ->assertOk()
        ->assertJsonPath('data.summary.total_critical_alerts', 0)
        ->assertJsonCount(0, 'data.alerts.unanswered_cases')
        ->assertJsonCount(0, 'data.alerts.poor_technicians');
});

test('admin alerts returns 500 when fetching fails', function () {
    $admin = adminUser();
    $client = clientUser();

    $unansweredCase = serviceCaseFor($client, [
        'title' => 'Caso para alerta',
        'status' => 'active',
    ]);
    $unansweredCase->forceFill([
        'created_at' => Carbon::now()->subHours(30),
        'updated_at' => Carbon::now()->subHours(30),
    ])->save();

    Event::listen('eloquent.retrieved: '.ServiceCase::class, function () {
        Event::forget('eloquent.retrieved: '.ServiceCase::class);
        throw new \Exception('Alerts failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/alerts')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to fetch system alerts')
        ->assertJsonStructure(['error']);
});
