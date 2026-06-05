<?php

use App\Models\CaseImage;
use App\Models\ServiceCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('client can create a service case', function () {
    Storage::fake('public');
    $client = clientUser();

    $response = $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/cases', [
            'title' => 'Reparar lavadora',
            'description' => 'No centrifuga correctamente.',
            'service_type' => 'presential',
            'city' => 'Manizales',
            'images' => [UploadedFile::fake()->image('case.jpg')],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Reparar lavadora');

    $this->assertDatabaseHas('service_cases', [
        'client_id' => $client->client->id,
        'title' => 'Reparar lavadora',
        'status' => 'active',
    ]);
});

test('client can list their service cases', function () {
    $client = clientUser();
    serviceCaseFor($client, ['title' => 'Caso uno']);
    serviceCaseFor($client, ['title' => 'Caso dos']);

    $otherClient = clientUser();
    serviceCaseFor($otherClient, ['title' => 'Caso ajeno']);

    $response = $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.data');
});

test('client can view update and delete an active case', function () {
    Storage::fake('public');
    $client = clientUser();
    $case = serviceCaseFor($client, [
        'title' => 'Caso original',
        'city' => 'Manizales',
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson("/api/client/cases/{$case->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $case->id);

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Caso actualizado',
            'description' => 'Descripcion actualizada.',
            'service_type' => 'remote',
            'city' => 'Manizales',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Caso actualizado');

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}")
        ->assertOk();

    $this->assertSoftDeleted('service_cases', ['id' => $case->id]);
});

test('client cannot modify a resolved case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Intento de cambio',
            'description' => 'No deberia permitirse.',
            'service_type' => 'presential',
            'city' => 'Manizales',
        ])
        ->assertForbidden();
});

test('client cannot access another clients case', function () {
    $client = clientUser();
    $otherClient = clientUser();
    $case = serviceCaseFor($otherClient);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson("/api/client/cases/{$case->id}")
        ->assertNotFound();
});

test('client can filter service cases by status and search', function () {
    $client = clientUser();
    serviceCaseFor($client, ['title' => 'Lavadora rota', 'status' => 'active']);
    serviceCaseFor($client, ['title' => 'Nevera dañada', 'status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.status', 'active');

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?search=Nevera')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.title', 'Nevera dañada');
});

test('client case endpoints require a client profile', function () {
    $user = clientRoleWithoutProfile();

    $this->actingAs($user, 'sanctum')->postJson('/api/client/cases', [
        'title' => 'Caso sin perfil',
        'description' => 'Descripcion.',
        'service_type' => 'presential',
        'city' => 'Manizales',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');

    $this->actingAs($user, 'sanctum')->getJson('/api/client/cases')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes un perfil de cliente asociado.');
});

test('creating a service case handles internal errors', function () {
    Storage::fake('public');
    $client = clientUser();

    Event::listen('eloquent.creating: '.ServiceCase::class, function () {
        throw new \Exception('Create failed');
    });

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/cases', [
            'title' => 'Caso con error',
            'description' => 'Descripcion.',
            'service_type' => 'presential',
            'city' => 'Manizales',
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Error al crear el caso de servicio.');
});

test('client can filter and sort service cases', function () {
    $client = clientUser();
    $technicianA = technicianUser(['name' => 'Ana Tecnica']);
    $technicianB = technicianUser(['name' => 'Zoe Tecnica']);

    $presentialCase = serviceCaseFor($client, [
        'title' => 'Caso presencial',
        'service_type' => 'presential',
        'status' => 'pending',
        'accepted_technician_id' => $technicianB->technician->id,
    ]);
    $remoteCase = serviceCaseFor($client, [
        'title' => 'Caso remoto',
        'service_type' => 'remote',
        'status' => 'active',
        'accepted_technician_id' => $technicianA->technician->id,
    ]);

    caseResponseFor($presentialCase, $technicianA);
    caseResponseFor($presentialCase, $technicianB);
    caseResponseFor($remoteCase, $technicianA);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?service_type=presential')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.service_type', 'presential');

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?sort_by=responses_count&sort_order=desc')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $presentialCase->id);

    $this
        ->actingAs($client, 'sanctum')
        ->getJson('/api/client/cases?sort_by=technician_name&sort_order=asc')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $remoteCase->id);
});

test('client can update a case with new images', function () {
    Storage::fake('public');
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Caso con imagen',
            'description' => 'Descripcion actualizada.',
            'service_type' => 'presential',
            'city' => 'Manizales',
            'images' => [UploadedFile::fake()->image('extra.jpg')],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Caso con imagen');

    $this->assertDatabaseHas('service_case_images', [
        'service_case_id' => $case->id,
    ]);
});

test('updating a service case handles internal errors', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    Event::listen('eloquent.updating: '.ServiceCase::class, function () {
        throw new \Exception('Update failed');
    });

    $this
        ->actingAs($client, 'sanctum')
        ->putJson("/api/client/cases/{$case->id}", [
            'title' => 'Caso fallido',
            'description' => 'Descripcion.',
            'service_type' => 'presential',
            'city' => 'Manizales',
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Error al actualizar el caso de servicio.');
});

test('client cannot delete a resolved case', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'resolved']);

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'No puedes eliminar un caso que ya ha sido respondido, resuelto o cancelado.');
});

test('client can delete a case with stored images', function () {
    Storage::fake('public');
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);
    $path = 'cases/images/delete-me.jpg';
    Storage::disk('public')->put($path, 'image-data');
    CaseImage::create([
        'service_case_id' => $case->id,
        'image_path' => $path,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Caso de servicio eliminado exitosamente.');

    $this->assertSoftDeleted('service_cases', ['id' => $case->id]);
    Storage::disk('public')->assertMissing($path);
});

test('deleting a service case handles internal errors', function () {
    $client = clientUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    Event::listen('eloquent.deleting: '.ServiceCase::class, function () {
        throw new \Exception('Delete failed');
    });

    $this
        ->actingAs($client, 'sanctum')
        ->deleteJson("/api/client/cases/{$case->id}")
        ->assertStatus(500)
        ->assertJsonPath('message', 'Error al eliminar el caso de servicio.');
});

test('creating a service case requires mandatory fields', function () {
    $client = clientUser();

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/cases', [])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseCount('service_cases', 0);
});
