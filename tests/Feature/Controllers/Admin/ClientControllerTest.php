<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('admin can list clients', function () {
    $admin = adminUser();
    clientUser();
    clientUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/clients');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['data']]);
});

test('admin can create a client', function () {
    $admin = adminUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/clients', [
            'name' => 'Cliente Admin',
            'email' => 'cliente.admin@example.com',
            'password' => 'password123',
            'phone' => '3004445566',
            'city' => 'Manizales',
            'address' => 'Calle 1',
            'type_id' => 'CC',
            'id_number' => '1098765432',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'cliente.admin@example.com')->first();
    expect($user->hasRole('client'))->toBeTrue();
    $this->assertDatabaseHas('clients', ['user_id' => $user->id]);
});

test('admin can show block and delete a client', function () {
    $admin = adminUser();
    $client = clientUser(['status' => 'active']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/clients/{$client->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/clients/{$client->id}")
        ->assertOk();

    $this->assertSoftDeleted('users', ['id' => $client->id]);
});

test('admin can update a client with all fields', function () {
    $admin = adminUser();
    $client = clientUser([
        'name' => 'Cliente Original',
        'phone' => '3004445566',
        'city' => 'Manizales',
        'address' => 'Calle 1',
        'type_id' => 'CC',
        'id_number' => '1098765432',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/clients/{$client->id}", [
            'name' => 'Cliente Editado',
            'email' => $client->email,
            'phone' => '3009998877',
            'city' => 'Bogota',
            'address' => 'Carrera 20',
            'type_id' => 'CC',
            'id_number' => $client->id_number,
            'status' => 'blocked',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Client updated successfully')
        ->assertJsonPath('data.name', 'Cliente Editado')
        ->assertJsonPath('data.status', 'blocked');

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => 'Cliente Editado',
        'city' => 'Bogota',
        'status' => 'blocked',
    ]);
});

test('admin can update client image and replace the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/old-client.jpg', 'old-image');

    $admin = adminUser();
    $client = clientUser(['image' => 'users/images/old-client.jpg']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->post("/api/admin/clients/{$client->id}", [
            '_method' => 'PUT',
            'name' => 'Cliente Con Imagen',
            'email' => $client->email,
            'phone' => $client->phone,
            'city' => $client->city,
            'address' => $client->address,
            'type_id' => $client->type_id,
            'id_number' => $client->id_number,
            'status' => 'active',
            'image' => UploadedFile::fake()->image('new-client.jpg'),
        ], ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Cliente Con Imagen');

    $client->refresh();
    expect($client->image)->not->toBe('users/images/old-client.jpg');
    Storage::disk('public')->assertMissing('users/images/old-client.jpg');
    Storage::disk('public')->assertExists($client->image);
});

test('admin update returns 500 when client is not found', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/clients/{$technician->id}", [
            'name' => 'No deberia actualizar',
        ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to update client');
});

test('client cannot access admin client management', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/admin/clients')
        ->assertForbidden();
});

test('admin can filter clients by city', function () {
    $admin = adminUser();
    clientUser(['city' => 'Manizales']);
    clientUser(['city' => 'Bogota']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/clients?city=Manizales')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.city', 'Manizales');
});

test('admin gets 404 when showing a non client user', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/clients/{$technician->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Client not found');
});

test('admin can filter clients by search and status', function () {
    $admin = adminUser();
    clientUser(['name' => 'Ana Perez', 'status' => 'active', 'city' => 'Manizales']);
    clientUser(['name' => 'Luis Gomez', 'status' => 'blocked', 'city' => 'Bogota']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/clients?search=Ana')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.name', 'Ana Perez');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/clients?status=blocked')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.status', 'blocked');
});

test('admin index returns 500 when listing fails', function () {
    $admin = adminUser();
    clientUser();

    Event::listen('eloquent.retrieved: '.User::class, function () {
        Event::forget('eloquent.retrieved: '.User::class);
        throw new \Exception('Listing failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/clients')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to list clients');
});

test('admin can create a client with image', function () {
    Storage::fake('public');
    $admin = adminUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/clients', [
            'name' => 'Cliente Con Foto',
            'email' => 'cliente.foto@example.com',
            'password' => 'password123',
            'phone' => '3004445566',
            'city' => 'Manizales',
            'address' => 'Calle 1',
            'type_id' => 'CC',
            'id_number' => '1098765433',
            'image' => UploadedFile::fake()->image('client.jpg'),
        ]);

    $response->assertCreated()->assertJsonPath('status', 'success');

    $user = User::where('email', 'cliente.foto@example.com')->first();
    expect($user->image)->not->toBeNull();
    Storage::disk('public')->assertExists($user->image);
});

test('admin store returns 500 when client creation fails', function () {
    $admin = adminUser();

    Event::listen('eloquent.creating: '.User::class, function () {
        Event::forget('eloquent.creating: '.User::class);
        throw new \Exception('Create failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/clients', [
            'name' => 'Cliente Fallido',
            'email' => 'cliente.fallido@example.com',
            'password' => 'password123',
            'phone' => '3004445566',
            'city' => 'Manizales',
            'address' => 'Calle 1',
            'type_id' => 'CC',
            'id_number' => '1098765434',
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to create client');
});

test('admin can unblock a client and logs the action', function () {
    $admin = adminUser();
    $client = clientUser(['status' => 'blocked']);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/clients/{$client->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'unblock_client',
        'target_id' => $client->id,
    ]);
});

test('admin block returns 500 when client is not found', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/clients/{$technician->id}/block")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to update client status');
});

test('admin can delete a client with stored image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/client-delete.jpg', 'image-data');

    $admin = adminUser();
    $client = clientUser(['image' => 'users/images/client-delete.jpg']);

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/clients/{$client->id}")
        ->assertOk();

    Storage::disk('public')->assertMissing('users/images/client-delete.jpg');
    $this->assertSoftDeleted('users', ['id' => $client->id]);
});

test('admin destroy returns 500 when client is not found', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson('/api/admin/clients/99999')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to delete client');
});
