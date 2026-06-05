<?php

use App\Models\TechnicianAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('technician can upload a tool asset and it is auto approved', function () {
    Storage::fake('public');
    $technician = technicianUser();

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/assets', [
            'type' => 'tool',
            'description' => 'Multimetro digital',
            'image' => UploadedFile::fake()->image('tool.jpg'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.type', 'tool')
        ->assertJsonPath('data.status', 'approved');

    $asset = TechnicianAsset::first();
    expect($asset)->not->toBeNull();
    Storage::disk('public')->assertExists($asset->image_path);
});

test('technician certification upload remains pending for admin review', function () {
    Storage::fake('public');
    $technician = technicianUser();

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/assets', [
            'type' => 'certification',
            'description' => 'Certificado tecnico',
            'image' => UploadedFile::fake()->image('certificate.png'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.type', 'certification')
        ->assertJsonPath('data.status', 'pending');
});

test('technician can only list their own assets', function () {
    $technician = technicianUser();
    $otherTechnician = technicianUser();

    $ownAsset = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/own.jpg',
        'description' => 'Own tool',
        'status' => 'approved',
    ]);

    TechnicianAsset::create([
        'technician_id' => $otherTechnician->technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/other.jpg',
        'description' => 'Other tool',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->getJson('/api/technician/assets');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownAsset->id);
});

test('technician cannot delete another technicians asset', function () {
    Storage::fake('public');
    $technician = technicianUser();
    $otherTechnician = technicianUser();

    $asset = TechnicianAsset::create([
        'technician_id' => $otherTechnician->technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/other.jpg',
        'description' => 'Other tool',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($technician, 'sanctum')
        ->deleteJson("/api/technician/assets/{$asset->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('technician_assets', ['id' => $asset->id]);
});

test('technician asset upload rejects invalid type', function () {
    Storage::fake('public');
    $technician = technicianUser();

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/assets', [
            'type' => 'invalid_type',
            'description' => 'Documento',
            'image' => UploadedFile::fake()->image('doc.jpg'),
        ])
        ->assertStatus(422);

    $this->assertDatabaseCount('technician_assets', 0);
});

test('technician asset upload requires an image', function () {
    $technician = technicianUser();

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/assets', [
            'type' => 'tool',
            'description' => 'Sin imagen',
        ])
        ->assertStatus(422);
});
