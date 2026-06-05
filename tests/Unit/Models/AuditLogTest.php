<?php

use App\Models\AuditLog;

test('audit log belongs to actor and casts json fields', function () {
    $actor = clientUser();

    $log = AuditLog::create([
        'actor_id' => $actor->id,
        'action' => 'create_case',
        'target_type' => 'App\\Models\\ServiceCase',
        'target_id' => 10,
        'description' => 'Creo un caso.',
        'old_values' => ['status' => 'active'],
        'new_values' => ['status' => 'resolved'],
    ]);

    expect($log->actor->id)->toBe($actor->id);
    expect($log->admin->id)->toBe($actor->id);
    expect($log->old_values)->toBe(['status' => 'active']);
    expect($log->new_values)->toBe(['status' => 'resolved']);
});

test('audit log actor includes soft deleted users', function () {
    $actor = adminUser();
    $actor->delete();

    $log = AuditLog::create([
        'actor_id' => $actor->id,
        'action' => 'delete_user',
        'description' => 'Usuario eliminado.',
    ]);

    expect($log->actor->id)->toBe($actor->id);
    expect($log->actor->trashed())->toBeTrue();
});
