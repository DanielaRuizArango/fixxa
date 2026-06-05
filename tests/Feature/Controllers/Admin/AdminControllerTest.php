<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('super admin can list admins with pagination', function () {
    $superAdmin = superAdminUser();
    adminUser();
    moderatorUser();

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->getJson('/api/admin/admins');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => ['data', 'current_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('data.per_page', 10)
        ->assertJsonCount(3, 'data.data');
});

test('super admin index returns 500 when listing fails', function () {
    $superAdmin = superAdminUser();
    adminUser();

    Event::listen('eloquent.retrieved: '.User::class, function () {
        throw new \Exception('Listing failed');
    });

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->getJson('/api/admin/admins')
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to list admin users');
});

test('super admin can create an admin user with default role', function () {
    $superAdmin = superAdminUser();

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->postJson('/api/admin/admins', [
            'name' => 'Admin Por Defecto',
            'email' => 'admin.defecto@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '3002223344',
            'city' => 'Manizales',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'admin.defecto@example.com')->first();
    expect($user->hasRole('admin'))->toBeTrue();
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

test('super admin can show an admin user', function () {
    $superAdmin = superAdminUser();
    $target = adminUser(['name' => 'Admin Target']);

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->getJson("/api/admin/admins/{$target->id}")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $target->id);
});

test('super admin can update an admin user with all fields', function () {
    $superAdmin = superAdminUser();
    $target = adminUser([
        'name' => 'Admin Target',
        'phone' => '3001112233',
        'city' => 'Manizales',
        'address' => 'Calle 10',
        'type_id' => 'CC',
        'id_number' => '1234567890',
    ]);

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/admin/admins/{$target->id}", [
            'name' => 'Admin Actualizado',
            'email' => $target->email,
            'phone' => '3009998877',
            'city' => 'Bogota',
            'address' => 'Carrera 15',
            'type_id' => 'CC',
            'id_number' => $target->id_number,
            'status' => 'blocked',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Admin user updated successfully')
        ->assertJsonPath('data.name', 'Admin Actualizado')
        ->assertJsonPath('data.status', 'blocked');

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'name' => 'Admin Actualizado',
        'city' => 'Bogota',
        'status' => 'blocked',
    ]);
});

test('super admin can update admin image and replace the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/old-avatar.jpg', 'old-image');

    $superAdmin = superAdminUser();
    $target = adminUser(['image' => 'users/images/old-avatar.jpg']);

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->post("/api/admin/admins/{$target->id}", [
            '_method' => 'PUT',
            'name' => 'Admin Con Imagen',
            'email' => $target->email,
            'phone' => $target->phone,
            'city' => $target->city,
            'address' => $target->address,
            'type_id' => $target->type_id,
            'id_number' => $target->id_number,
            'status' => 'active',
            'image' => UploadedFile::fake()->image('new-avatar.jpg'),
        ], ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Admin Con Imagen');

    $target->refresh();
    expect($target->image)->not->toBe('users/images/old-avatar.jpg');
    Storage::disk('public')->assertMissing('users/images/old-avatar.jpg');
    Storage::disk('public')->assertExists($target->image);
});

test('super admin update returns 500 when admin is not found', function () {
    $superAdmin = superAdminUser();
    $client = clientUser();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/admin/admins/{$client->id}", [
            'name' => 'No deberia actualizar',
        ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to update admin user');
});

test('super admin gets 404 when showing a non admin user', function () {
    $superAdmin = superAdminUser();
    $client = clientUser();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->getJson("/api/admin/admins/{$client->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Admin user not found');
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
        ->patchJson("/api/admin/admins/{$target->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

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

test('super admin can create an admin user with image', function () {
    Storage::fake('public');
    $superAdmin = superAdminUser();

    $response = $this
        ->actingAs($superAdmin, 'sanctum')
        ->postJson('/api/admin/admins', [
            'name' => 'Admin Con Foto',
            'email' => 'admin.foto@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '3001112233',
            'city' => 'Manizales',
            'image' => UploadedFile::fake()->image('admin.jpg'),
        ]);

    $response->assertCreated()->assertJsonPath('status', 'success');

    $user = User::where('email', 'admin.foto@example.com')->first();
    expect($user->image)->not->toBeNull();
    Storage::disk('public')->assertExists($user->image);
});

test('super admin store returns 500 when creation fails', function () {
    $superAdmin = superAdminUser();

    Event::listen('eloquent.creating: '.User::class, function () {
        Event::forget('eloquent.creating: '.User::class);
        throw new \Exception('Create failed');
    });

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->postJson('/api/admin/admins', [
            'name' => 'Admin Fallido',
            'email' => 'admin.fallido@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '3001112233',
            'city' => 'Manizales',
        ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to create admin user');
});

test('super admin block returns 500 when admin is not found', function () {
    $superAdmin = superAdminUser();
    $client = clientUser();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->patchJson("/api/admin/admins/{$client->id}/block")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to update admin status');
});

test('super admin destroy returns 500 when admin is not found', function () {
    $superAdmin = superAdminUser();

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->deleteJson('/api/admin/admins/99999')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to delete admin user');
});

test('super admin can delete an admin with stored image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/admin-to-delete.jpg', 'image-data');

    $superAdmin = superAdminUser();
    $target = adminUser(['image' => 'users/images/admin-to-delete.jpg']);

    $this
        ->actingAs($superAdmin, 'sanctum')
        ->deleteJson("/api/admin/admins/{$target->id}")
        ->assertOk();

    Storage::disk('public')->assertMissing('users/images/admin-to-delete.jpg');
    $this->assertSoftDeleted('users', ['id' => $target->id]);
});
