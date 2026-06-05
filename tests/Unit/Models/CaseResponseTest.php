<?php

use App\Models\CaseResponse;

test('case response belongs to service case and technician', function () {
    $client = clientUser();
    $technician = technicianUser();
    $case = serviceCaseFor($client);
    $response = caseResponseFor($case, $technician);

    $loaded = CaseResponse::with(['serviceCase', 'technician.user'])->find($response->id);

    expect($loaded->serviceCase->id)->toBe($case->id);
    expect($loaded->technician->user->id)->toBe($technician->id);
});

test('case response uses soft deletes', function () {
    $response = caseResponseFor(serviceCaseFor(clientUser()), technicianUser());
    $response->delete();

    $this->assertSoftDeleted('case_responses', ['id' => $response->id]);
});
