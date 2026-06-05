<?php

use App\Models\User;

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

test('admin can show update block and delete a client', function () {
    $admin = adminUser();
    $client = clientUser(['status' => 'active']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/clients/{$client->id}", [
            'name' => 'Cliente Editado',
            'email' => $client->email,
            'phone' => $client->phone,
            'city' => $client->city,
            'address' => $client->address,
            'type_id' => $client->type_id,
            'id_number' => $client->id_number,
            'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Cliente Editado');

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

test('client cannot access admin client management', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/admin/clients')
        ->assertForbidden();
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
