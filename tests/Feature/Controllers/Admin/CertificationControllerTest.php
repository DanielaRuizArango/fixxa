<?php

use App\Models\TechnicianAsset;

test('admin can list approve and reject certifications', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $certification = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.png',
        'description' => 'Certificado HVAC',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/certifications?status=pending')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.data.0.id', $certification->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/certifications/{$certification->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $certification->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/certifications/{$certification->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $rejected = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert2.png',
        'description' => 'Certificado vencido',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/certifications/{$rejected->id}/reject", [
            'rejection_reason' => 'Documento ilegible.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('admin can manage id documents', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $document = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.png',
        'description' => 'Cedula',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/id-documents?status=pending')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $document->id);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/id-documents/{$document->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

test('admin can reject an id document with a reason', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $document = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id-reject.png',
        'description' => 'Cedula borrosa',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/id-documents/{$document->id}/reject", [
            'rejection_reason' => 'Imagen no legible.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Imagen no legible.');
});

test('rejecting certification requires a reason', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $certification = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert3.png',
        'description' => 'Certificado',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/certifications/{$certification->id}/reject", [])
        ->assertStatus(422);
});
