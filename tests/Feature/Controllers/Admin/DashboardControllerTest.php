<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('admin_dashboard_metrics');
});

test('admin can fetch dashboard metrics', function () {
    $admin = adminUser();
    clientUser();
    technicianUser();
    serviceCaseFor(clientUser(), ['status' => 'active']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'cases',
                'technicians',
                'recent_clients',
                'recent_logs',
                'completed_services',
                'total_users',
            ],
        ]);
});

test('admin can fetch paginated audit logs', function () {
    $admin = adminUser();

    AuditLog::create([
        'actor_id' => clientUser()->id,
        'action' => 'create_case',
        'target_type' => 'App\\Models\\ServiceCase',
        'target_id' => 1,
        'description' => 'Cliente creo un caso.',
    ]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/logs?search=create_case');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['data']]);
});

test('guest cannot access dashboard endpoints', function () {
    $this->getJson('/api/admin/dashboard/metrics')->assertUnauthorized();
    $this->getJson('/api/admin/logs')->assertUnauthorized();
});

test('admin can filter audit logs by action and date range', function () {
    $admin = adminUser();
    $client = clientUser();

    $log = AuditLog::create([
        'actor_id' => $client->id,
        'action' => 'block_client',
        'target_type' => 'User',
        'target_id' => $client->id,
        'description' => 'Bloqueo de prueba',
        'created_at' => now()->subDays(2),
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/logs?action=block_client&date_from='.now()->subDays(3)->toDateString().'&date_to='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $log->id);
});

test('regular admin metrics exclude other admin audit logs', function () {
    $admin = adminUser();
    $client = clientUser();

    $clientLog = AuditLog::create([
        'actor_id' => $client->id,
        'action' => 'create_case',
        'target_type' => 'App\\Models\\ServiceCase',
        'target_id' => 1,
        'description' => 'Cliente creo un caso.',
    ]);

    $adminLog = AuditLog::create([
        'actor_id' => $admin->id,
        'action' => 'admin_action',
        'target_type' => 'User',
        'target_id' => 1,
        'description' => 'Accion interna de admin.',
    ]);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics');

    $logIds = collect($response->json('data.recent_logs'))->pluck('id');

    expect($logIds)->toContain($clientLog->id);
    expect($logIds)->not->toContain($adminLog->id);
});

test('moderator can access dashboard and client management', function () {
    $moderator = moderatorUser();
    clientUser();

    $this
        ->actingAs($moderator, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics')
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this
        ->actingAs($moderator, 'sanctum')
        ->getJson('/api/admin/clients')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
