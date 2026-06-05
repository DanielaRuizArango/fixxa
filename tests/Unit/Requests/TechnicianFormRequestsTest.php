<?php

use App\Http\Requests\Api\Technician\RegisterRequest;
use App\Http\Requests\Api\Technician\StoreCaseResponseRequest;
use Illuminate\Support\Facades\Validator;

test('store case response request authorizes only technicians', function () {
    $technician = technicianUser();
    $client = clientUser();

    $technicianRequest = StoreCaseResponseRequest::create('/api/technician/responses', 'POST');
    $technicianRequest->setUserResolver(fn () => $technician);

    $clientRequest = StoreCaseResponseRequest::create('/api/technician/responses', 'POST');
    $clientRequest->setUserResolver(fn () => $client);

    expect($technicianRequest->authorize())->toBeTrue();
    expect($clientRequest->authorize())->toBeFalse();
});

test('store case response request validates cost and case id', function () {
    $case = serviceCaseFor(clientUser());

    $invalid = Validator::make([
        'service_case_id' => $case->id,
        'estimated_cost' => -10,
    ], (new StoreCaseResponseRequest())->rules());

    $valid = Validator::make([
        'service_case_id' => $case->id,
        'estimated_cost' => 50000,
        'questions' => 'Disponible hoy',
    ], (new StoreCaseResponseRequest())->rules());

    expect($invalid->errors()->has('estimated_cost'))->toBeTrue();
    expect($valid->passes())->toBeTrue();
});

test('technician register request requires experience and title', function () {
    $validator = Validator::make([
        'name' => 'Tecnico',
        'email' => 'nuevo.tecnico@example.com',
        'phone' => '3002223344',
        'address' => 'Calle 2',
        'city' => 'Manizales',
        'type_id' => 'CC',
        'id_number' => '9876543210',
        'password' => 'password123',
    ], (new RegisterRequest())->rules());

    expect($validator->errors()->has('experience'))->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
});
