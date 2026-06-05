<?php

use App\Models\User;

test('admin can list technicians', function () {
    $admin = adminUser();
    technicianUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['data']]);
});

test('admin can create a technician', function () {
    $admin = adminUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/technicians', [
            'name' => 'Tecnico Admin',
            'email' => 'tecnico.admin@example.com',
            'password' => 'password123',
            'phone' => '3007778899',
            'city' => 'Manizales',
            'address' => 'Calle 2',
            'type_id' => 'CC',
            'id_number' => '1087654321',
            'experience' => '5 anos de experiencia.',
            'title' => 'Tecnico en sistemas',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'tecnico.admin@example.com')->first();
    expect($user->hasRole('technician'))->toBeTrue();
    $this->assertDatabaseHas('technicians', ['user_id' => $user->id]);
});

test('admin can show update block and delete a technician', function () {
    $admin = adminUser();
    $technicianUser = technicianUser(['status' => 'active']);
    $technician = $technicianUser->technician;

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/technicians/{$technician->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $technician->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/technicians/{$technicianUser->id}", [
            'name' => 'Tecnico Editado',
            'email' => $technicianUser->email,
            'phone' => $technicianUser->phone,
            'city' => $technicianUser->city,
            'address' => $technicianUser->address,
            'type_id' => $technicianUser->type_id,
            'id_number' => $technicianUser->id_number,
            'status' => 'active',
            'experience' => 'Experiencia actualizada.',
            'title' => 'Especialista',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Tecnico Editado');

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/technicians/{$technicianUser->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/technicians/{$technicianUser->id}")
        ->assertOk();

    $this->assertSoftDeleted('users', ['id' => $technicianUser->id]);
});

test('admin can filter technicians by search', function () {
    $admin = adminUser();
    technicianUser(['name' => 'Carlos Tecnico']);
    technicianUser(['name' => 'Maria Instaladora']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians?search=Carlos')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.user.name', 'Carlos Tecnico');
});
