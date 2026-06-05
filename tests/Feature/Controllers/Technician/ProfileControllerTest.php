<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('technician can view their profile', function () {
    $technician = technicianUser();

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/me');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $technician->id)
        ->assertJsonPath('data.technician.id', $technician->technician->id);
});

test('technician can update their profile', function () {
    Storage::fake('public');
    $technician = technicianUser();

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/profile', [
            'name' => 'Tecnico Actualizado',
            'email' => 'tecnico.actualizado@example.com',
            'phone' => '3109876543',
            'address' => 'Carrera 23 #45-67',
            'city' => 'Manizales',
            'experience' => '10 anos en electrodomesticos.',
            'title' => 'Tecnico Senior',
            'is_available' => true,
            'working_hours' => 'Lunes a Viernes 8am - 6pm',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Perfil de técnico actualizado exitosamente.');

    $this->assertDatabaseHas('users', [
        'id' => $technician->id,
        'name' => 'Tecnico Actualizado',
    ]);

    $this->assertDatabaseHas('technicians', [
        'id' => $technician->technician->id,
        'title' => 'Tecnico Senior',
    ]);
});

test('client cannot access technician profile routes', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/technician/me')
        ->assertForbidden();
});

test('technician can replace profile image and delete the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/old-tech.jpg', 'old-image');

    $technician = technicianUser(['image' => 'users/images/old-tech.jpg']);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/profile', [
            'name' => $technician->name,
            'email' => $technician->email,
            'phone' => $technician->phone,
            'address' => $technician->address,
            'city' => $technician->city,
            'experience' => '5 anos',
            'title' => 'Tecnico',
            'image' => UploadedFile::fake()->image('new-tech.jpg'),
        ])
        ->assertOk();

    $technician->refresh();
    expect($technician->image)->not->toBe('users/images/old-tech.jpg');
    Storage::disk('public')->assertMissing('users/images/old-tech.jpg');
    Storage::disk('public')->assertExists($technician->image);
});

test('technician can update profile password', function () {
    $technician = technicianUser(['password' => Hash::make('old-password')]);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/profile', [
            'name' => $technician->name,
            'email' => $technician->email,
            'phone' => $technician->phone,
            'address' => $technician->address,
            'city' => $technician->city,
            'experience' => '5 anos',
            'title' => 'Tecnico',
            'password' => 'new-secure-password',
        ])
        ->assertOk();

    expect(Hash::check('new-secure-password', $technician->fresh()->password))->toBeTrue();
});

test('technician update preserves availability and working hours when omitted', function () {
    $technician = technicianUser();
    $technician->technician->update([
        'is_available' => false,
        'working_hours' => 'Lunes a Viernes',
    ]);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/profile', [
            'name' => 'Tecnico Sin Cambios',
            'email' => $technician->email,
            'phone' => $technician->phone,
            'address' => $technician->address,
            'city' => $technician->city,
            'experience' => '8 anos',
            'title' => 'Tecnico Senior',
        ])
        ->assertOk();

    $technician->technician->refresh();
    expect($technician->technician->is_available)->toBeFalse();
    expect($technician->technician->working_hours)->toBe('Lunes a Viernes');
});

test('client cannot update technician profile', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/technician/profile', [
            'name' => 'Cliente',
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'city' => $client->city,
            'experience' => '0',
            'title' => 'N/A',
        ])
        ->assertForbidden();
});
