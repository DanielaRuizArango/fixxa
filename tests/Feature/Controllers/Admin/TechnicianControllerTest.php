<?php

use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('admin can list technicians with pagination', function () {
    $admin = adminUser();
    technicianUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => ['data', 'current_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('data.per_page', 50);
});

test('admin index returns 500 when listing fails', function () {
    $admin = adminUser();
    technicianUser();

    Event::listen('eloquent.retrieved: '.Technician::class, function () {
        throw new \Exception('Listing failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians')
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to list technicians');
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

test('admin can create a technician with image', function () {
    Storage::fake('public');
    $admin = adminUser();

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->post('/api/admin/technicians', [
            'name' => 'Tecnico Con Foto',
            'email' => 'tecnico.foto@example.com',
            'password' => 'password123',
            'phone' => '3008889900',
            'city' => 'Manizales',
            'address' => 'Calle 3',
            'type_id' => 'CC',
            'id_number' => '1087654322',
            'experience' => '3 anos de experiencia.',
            'title' => 'Electricista',
            'image' => UploadedFile::fake()->image('tecnico.jpg'),
        ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'tecnico.foto@example.com')->first();
    expect($user->image)->not->toBeNull();
    Storage::disk('public')->assertExists($user->image);
});

test('admin store returns 500 when creation fails', function () {
    $admin = adminUser();

    Event::listen('eloquent.creating: '.User::class, function () {
        throw new \Exception('Create failed');
    });

    $this
        ->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/technicians', [
            'name' => 'Tecnico Fallido',
            'email' => 'tecnico.fallido@example.com',
            'password' => 'password123',
            'phone' => '3007778899',
            'city' => 'Manizales',
            'address' => 'Calle 2',
            'type_id' => 'CC',
            'id_number' => '1087654399',
            'experience' => '5 anos de experiencia.',
            'title' => 'Tecnico en sistemas',
        ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to create technician');
});

test('admin can show a technician', function () {
    $admin = adminUser();
    $technicianUser = technicianUser();
    $technician = $technicianUser->technician;

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/technicians/{$technician->id}")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $technician->id);
});

test('admin can update a technician with all fields', function () {
    $admin = adminUser();
    $technicianUser = technicianUser(['status' => 'active']);

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/technicians/{$technicianUser->id}", [
            'name' => 'Tecnico Editado',
            'email' => $technicianUser->email,
            'phone' => '3009998877',
            'city' => 'Bogota',
            'address' => 'Carrera 25',
            'type_id' => $technicianUser->type_id,
            'id_number' => $technicianUser->id_number,
            'status' => 'blocked',
            'experience' => 'Experiencia actualizada.',
            'title' => 'Especialista',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Technician updated successfully')
        ->assertJsonPath('data.name', 'Tecnico Editado')
        ->assertJsonPath('data.status', 'blocked')
        ->assertJsonPath('data.technician.title', 'Especialista');
});

test('admin can update technician image and replace the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/old-tech.jpg', 'old-image');

    $admin = adminUser();
    $technicianUser = technicianUser(['image' => 'users/images/old-tech.jpg']);

    $response = $this
        ->actingAs($admin, 'sanctum')
        ->post("/api/admin/technicians/{$technicianUser->id}", [
            '_method' => 'PUT',
            'name' => 'Tecnico Con Imagen',
            'email' => $technicianUser->email,
            'phone' => $technicianUser->phone,
            'city' => $technicianUser->city,
            'address' => $technicianUser->address,
            'type_id' => $technicianUser->type_id,
            'id_number' => $technicianUser->id_number,
            'status' => 'active',
            'image' => UploadedFile::fake()->image('new-tech.jpg'),
        ], ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Tecnico Con Imagen');

    $technicianUser->refresh();
    expect($technicianUser->image)->not->toBe('users/images/old-tech.jpg');
    Storage::disk('public')->assertMissing('users/images/old-tech.jpg');
    Storage::disk('public')->assertExists($technicianUser->image);
});

test('admin update returns 500 when technician is not found', function () {
    $admin = adminUser();
    $client = clientUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/technicians/{$client->id}", [
            'name' => 'No deberia actualizar',
        ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Failed to update technician');
});

test('admin can block and delete a technician', function () {
    $admin = adminUser();
    $technicianUser = technicianUser(['status' => 'active']);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/technicians/{$technicianUser->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/technicians/{$technicianUser->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Technician deleted successfully');

    $this->assertSoftDeleted('users', ['id' => $technicianUser->id]);
});

test('admin can delete a technician with image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/tech-delete.jpg', 'image');

    $admin = adminUser();
    $technicianUser = technicianUser(['image' => 'users/images/tech-delete.jpg']);

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/technicians/{$technicianUser->id}")
        ->assertOk();

    Storage::disk('public')->assertMissing('users/images/tech-delete.jpg');
    $this->assertSoftDeleted('users', ['id' => $technicianUser->id]);
});

test('admin can unblock a blocked technician', function () {
    $admin = adminUser();
    $technicianUser = technicianUser(['status' => 'blocked']);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/technicians/{$technicianUser->id}/block")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('admin block returns 500 when technician is not found', function () {
    $admin = adminUser();
    $client = clientUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/technicians/{$client->id}/block")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to update technician status');
});

test('admin destroy returns 500 when technician is not found', function () {
    $admin = adminUser();
    $client = clientUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/technicians/{$client->id}")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to delete technician');
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

test('admin can filter technicians by city and status', function () {
    $admin = adminUser();
    technicianUser(['city' => 'Manizales', 'status' => 'active']);
    technicianUser(['city' => 'Bogota', 'status' => 'blocked']);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians?city=Manizales')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.user.city', 'Manizales');

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians?status=blocked')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.user.status', 'blocked');
});

test('admin gets 404 when showing a non existent technician', function () {
    $admin = adminUser();

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/technicians/99999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Technician not found');
});

test('client cannot access admin technician management', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/admin/technicians')
        ->assertForbidden();
});
