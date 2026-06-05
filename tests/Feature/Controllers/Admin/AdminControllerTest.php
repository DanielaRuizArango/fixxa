<?php

use App\Models\User;

test('super admin can list admins', function () {
    $superAdmin = superAdminUser();
    adminUser();

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->getJson('/api/admin/admins');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['data']]);
});

test('super admin can create an admin user', function () {
    $superAdmin = superAdminUser();

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->postJson('/api/admin/admins', [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '3001112233',
            'city' => 'Manizales',
            'spatie_role' => 'moderator',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'nuevo.admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('moderator'))->toBeTrue();
    $this->assertDatabaseHas('admins', ['user_id' => $user->id]);
});

test('super admin can block and delete another admin', function () {
    $superAdmin = superAdminUser();
    $target = adminUser(['status' => 'active']);

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->patchJson("/api/admin/admins/{$target->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->deleteJson("/api/admin/admins/{$target->id}")
        ->assertOk();

    $this->assertSoftDeleted('users', ['id' => $target->id]);
});

test('admin and moderator cannot manage admins resource', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/admins')
        ->assertForbidden();

    $moderator = moderatorUser();

    $this
        ->actingAs($moderator, 'sanctum')
        ->getJson('/api/admin/admins')
        ->assertForbidden();
});

test('super admin cannot block or delete own account', function () {
    $superAdmin = superAdminUser();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->patchJson("/api/admin/admins/{$superAdmin->id}/block")
        ->assertForbidden();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->deleteJson("/api/admin/admins/{$superAdmin->id}")
        ->assertForbidden();
});
