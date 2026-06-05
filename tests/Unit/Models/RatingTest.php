<?php

use App\Models\Rating;

test('rating belongs to service case client and technician', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client, [
        'status' => 'resolved',
        'accepted_technician_id' => $technician->technician->id,
    ]);

    $rating = Rating::create([
        'service_case_id' => $case->id,
        'client_id' => $client->client->id,
        'technician_id' => $technician->technician->id,
        'score' => 5,
        'comment' => 'Muy bueno',
    ]);

    $loaded = Rating::with(['serviceCase', 'client.user', 'technician.user'])->find($rating->id);

    expect($loaded->serviceCase->id)->toBe($case->id);
    expect($loaded->client->user->id)->toBe($client->id);
    expect($loaded->technician->user->id)->toBe($technician->id);
    expect($loaded->getTable())->toBe('service_ratings');
});
