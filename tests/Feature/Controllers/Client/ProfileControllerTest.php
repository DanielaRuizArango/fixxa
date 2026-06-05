<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('client can view their profile', function () {
    $client = clientUser();

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/me');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.email', $client->email);
});

test('client can update their profile', function () {
    Storage::fake('public');
    $client = clientUser();

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/profile', [
            'name' => 'Cliente Actualizado',
            'email' => 'cliente.actualizado@example.com',
            'phone' => '3001234567',
            'address' => 'Calle 10 #20-30',
            'city' => 'Manizales',
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Perfil actualizado exitosamente.');

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => 'Cliente Actualizado',
        'email' => 'cliente.actualizado@example.com',
    ]);
});

test('guest cannot access client profile', function () {
    $this->getJson('/api/client/me')->assertUnauthorized();
});

test('client can update profile password', function () {
    $client = clientUser(['password' => Hash::make('old-password')]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/profile', [
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'city' => $client->city,
            'password' => 'new-secure-password',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Hash::check('new-secure-password', $client->fresh()->password))->toBeTrue();
});

test('technician cannot access client profile routes', function () {
    $technician = technicianUser();

    $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/client/me')
        ->assertForbidden();
});
