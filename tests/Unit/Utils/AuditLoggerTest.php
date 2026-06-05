<?php

use App\Models\AuditLog;
use App\Utils\AuditLogger;

test('audit logger creates log with authenticated actor', function () {
    $client = clientUser();

    $this->actingAs($client, 'sanctum');

    $log = AuditLogger::log(
        'create_case',
        'App\\Models\\ServiceCase',
        42,
        'El cliente creo un caso.',
        ['status' => 'active'],
        ['status' => 'pending']
    );

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->actor_id)->toBe($client->id);
    expect($log->action)->toBe('create_case');
    expect($log->target_type)->toBe('App\\Models\\ServiceCase');
    expect($log->target_id)->toBe(42);
    expect($log->description)->toBe('El cliente creo un caso.');
    expect($log->old_values)->toBe(['status' => 'active']);
    expect($log->new_values)->toBe(['status' => 'pending']);

    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id,
        'actor_id' => $client->id,
        'action' => 'create_case',
    ]);
});

test('audit logger uses current authenticated user as actor', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'sanctum');

    $log = AuditLogger::log('admin_action', 'User', $admin->id, 'Accion de admin.');

    expect($log->actor_id)->toBe($admin->id);
    expect($log->ip_address)->not->toBeNull();
});
