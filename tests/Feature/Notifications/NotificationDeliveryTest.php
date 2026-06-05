<?php

use App\Models\TechnicianAsset;

test('client receives notification when technician responds to a case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, ['status' => 'active']);

    $this
        ->actingAs($technician, 'sanctum')
        ->postJson('/api/technician/responses', [
            'service_case_id' => $case->id,
            'estimated_cost' => 150000,
            'questions' => 'Puedo ir el lunes.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $client->id,
        'notifiable_type' => \App\Models\User::class,
    ]);

    $notification = $client->fresh()->notifications->first();
    expect($notification->data['type'])->toBe('case_responded');
    expect($notification->data['service_case_id'])->toBe($case->id);
});

test('technician receives notification when client rates a resolved case', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $this
        ->actingAs($client, 'sanctum')
        ->postJson('/api/client/ratings', [
            'service_case_id' => $case->id,
            'score' => 5,
            'comment' => 'Excelente trabajo.',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $technician->id,
        'notifiable_type' => \App\Models\User::class,
    ]);

    $notification = $technician->fresh()->notifications->first();
    expect($notification->data['type'])->toBe('technician_rated');
    expect($notification->data['score'])->toBe(5);
});

test('technician receives notification when admin approves a certification', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $asset = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert-approved.png',
        'description' => 'Curso electricidad',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/certifications/{$asset->id}/approve")
        ->assertOk();

    $notification = $technician->fresh()->notifications->first();
    expect($notification)->not->toBeNull();
    expect($notification->data['type'])->toBe('certification_reviewed');
    expect($notification->data['status'])->toBe('approved');
});

test('technician receives notification when admin rejects a certification', function () {
    $admin = adminUser();
    $technician = technicianUser();

    $asset = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert-rejected.png',
        'description' => 'Diploma vencido',
        'status' => 'pending',
    ]);

    $this
        ->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/certifications/{$asset->id}/reject", [
            'rejection_reason' => 'Documento expirado.',
        ])
        ->assertOk();

    $notification = $technician->fresh()->notifications->first();
    expect($notification->data['type'])->toBe('certification_reviewed');
    expect($notification->data['status'])->toBe('rejected');
    expect($notification->data['rejection_reason'])->toBe('Documento expirado.');
});
