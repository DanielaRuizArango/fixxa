<?php

use Illuminate\Http\UploadedFile;
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
