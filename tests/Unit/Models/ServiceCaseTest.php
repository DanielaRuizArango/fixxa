<?php

use App\Models\CaseImage;
use App\Models\CaseResponse;
use App\Models\Rating;
use App\Models\ServiceCase;

test('service case has client images responses rating and accepted technician', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'accepted_technician_id' => $technician->technician->id,
        'status' => 'resolved',
    ]);

    CaseImage::create([
        'service_case_id' => $case->id,
        'image_path' => 'cases/images/photo.jpg',
    ]);
    caseResponseFor($case, $technician);
    Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
    ]);

    $loaded = ServiceCase::with([
        'client.user',
        'images',
        'responses',
        'rating',
        'acceptedTechnician.user',
    ])->find($case->id);

    expect($loaded->client->user->id)->toBe($client->id);
    expect($loaded->images)->toHaveCount(1);
    expect($loaded->responses)->toHaveCount(1);
    expect($loaded->rating->score)->toBe(5);
    expect($loaded->acceptedTechnician->id)->toBe($technician->technician->id);
});

test('service case uses soft deletes', function () {
    $case = serviceCaseFor(clientUser());
    $case->delete();

    $this->assertSoftDeleted('service_cases', ['id' => $case->id]);
});
