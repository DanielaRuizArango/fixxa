<?php

use App\Models\Rating;
use App\Models\Technician;
use App\Models\TechnicianAsset;

test('technician belongs to user and has domain relations', function () {
    $user = technicianUser();
    $technician = $user->technician;
    $client = clientUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->id,
    ]);

    caseResponseFor($case, $user);
    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->id,
        'score' => 4,
    ]);
    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'tool',
        'image_path' => 'technicians/assets/tool.jpg',
        'status' => 'approved',
    ]);

    expect($technician->user->id)->toBe($user->id);
    expect($technician->caseResponses)->toHaveCount(1);
    expect($technician->ratings)->toHaveCount(1);
    expect($technician->assets)->toHaveCount(1);
});

test('technician calculates average rating and count', function () {
    $technicianUser = technicianUser();
    $technician = $technicianUser->technician;
    $client = clientUser();

    foreach ([4, 5] as $score) {
        $case = serviceCaseFor($client, [
            'status' => 'resolved',
            'accepted_technician_id' => $technician->id,
        ]);

        Rating::create([
            'service_case_id' => $case->id,
            'client_id' => $client->client->id,
            'technician_id' => $technician->id,
            'score' => $score,
        ]);
    }

    $fresh = Technician::withAvg('ratings', 'score')
        ->withCount('ratings')
        ->find($technician->id);

    expect($fresh->average_rating)->toBe(4.5);
    expect($fresh->ratings_count)->toBe(2);
});

test('technician casts is available as boolean', function () {
    $technician = technicianUser()->technician;
    $technician->update(['is_available' => 1]);

    expect($technician->fresh()->is_available)->toBeBool()->toBeTrue();
});

test('technician is not verified without documents', function () {
    $technician = technicianUser()->technician;

    expect($technician->fresh()->is_verified)->toBeFalse();
});

test('technician is not verified with only approved id document', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.jpg',
        'status' => 'approved',
    ]);

    expect($technician->fresh()->is_verified)->toBeFalse();
});

test('technician is not verified with approved id and pending certification', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.jpg',
        'status' => 'approved',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.jpg',
        'status' => 'pending',
    ]);

    expect($technician->fresh()->is_verified)->toBeFalse();
});

test('technician is not verified with approved id and rejected certification', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.jpg',
        'status' => 'approved',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.jpg',
        'status' => 'rejected',
        'rejection_reason' => 'Vencido.',
    ]);

    expect($technician->fresh()->is_verified)->toBeFalse();
});

test('technician is verified with approved id and all certifications approved', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.jpg',
        'status' => 'approved',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert1.jpg',
        'status' => 'approved',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert2.jpg',
        'status' => 'approved',
    ]);

    expect($technician->fresh()->is_verified)->toBeTrue();
});

test('technician is not verified with rejected id even if certifications are approved', function () {
    $technician = technicianUser()->technician;

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'id_document',
        'image_path' => 'technicians/assets/id.jpg',
        'status' => 'rejected',
        'rejection_reason' => 'Ilegible.',
    ]);

    TechnicianAsset::create([
        'technician_id' => $technician->id,
        'type' => 'certification',
        'image_path' => 'technicians/assets/cert.jpg',
        'status' => 'approved',
    ]);

    expect($technician->fresh()->is_verified)->toBeFalse();
});
