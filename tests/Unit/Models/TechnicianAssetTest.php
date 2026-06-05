<?php

use App\Models\TechnicianAsset;

test('technician asset scopes filter by type', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.png',
        'status' => 'pending',
    ]);
    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.png',
        'status' => 'pending',
    ]);
    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/tool.png',
        'status' => 'approved',
    ]);

    expect(TechnicianAsset::certifications()->count())->toBe(1);
    expect(TechnicianAsset::idDocuments()->count())->toBe(1);
});

test('technician asset belongs to technician and reviewer', function () {
    $technician = technicianUser();
    $admin = adminUser();

    $asset = TechnicianAsset::create([
        'technician_id' => $technician->technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.png',
        'status' => 'approved',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $loaded = TechnicianAsset::with(['technician.user', 'reviewer'])->find($asset->id);

    expect($loaded->technician->user->id)->toBe($technician->id);
    expect($loaded->reviewer->id)->toBe($admin->id);
    expect($loaded->reviewed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
