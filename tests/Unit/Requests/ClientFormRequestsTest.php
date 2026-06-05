<?php

use App\Http\Requests\Api\Client\RegisterRequest;
use App\Http\Requests\Api\Client\StoreRatingRequest;
use App\Http\Requests\Api\Client\StoreServiceCaseRequest;
use Illuminate\Support\Facades\Validator;

test('store service case request authorizes only clients', function () {
    $client = clientUser();
    $technician = technicianUser();

    $clientRequest = StoreServiceCaseRequest::create('/api/client/cases', 'POST');
    $clientRequest->setUserResolver(fn () => $client);

    $technicianRequest = StoreServiceCaseRequest::create('/api/client/cases', 'POST');
    $technicianRequest->setUserResolver(fn () => $technician);

    expect($clientRequest->authorize())->toBeTrue();
    expect($technicianRequest->authorize())->toBeFalse();
});

test('store service case request validates required fields', function () {
    $validator = Validator::make([], (new StoreServiceCaseRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('title', 'description', 'service_type', 'city');
});

test('store service case request rejects invalid service type', function () {
    $validator = Validator::make([
        'title' => 'Caso',
        'description' => 'Detalle',
        'service_type' => 'invalid',
        'city' => 'Manizales',
    ], (new StoreServiceCaseRequest())->rules());

    expect($validator->errors()->has('service_type'))->toBeTrue();
});

test('store rating request authorizes only clients', function () {
    $client = clientUser();
    $technician = technicianUser();

    $clientRequest = StoreRatingRequest::create('/api/client/ratings', 'POST');
    $clientRequest->setUserResolver(fn () => $client);

    $technicianRequest = StoreRatingRequest::create('/api/client/ratings', 'POST');
    $technicianRequest->setUserResolver(fn () => $technician);

    expect($clientRequest->authorize())->toBeTrue();
    expect($technicianRequest->authorize())->toBeFalse();
});

test('store rating request validates score between 1 and 5', function () {
    $case = serviceCaseFor(clientUser());

    $invalid = Validator::make([
        'service_case_id' => $case->id,
        'score' => 0,
    ], (new StoreRatingRequest())->rules());

    $valid = Validator::make([
        'service_case_id' => $case->id,
        'score' => 5,
        'comment' => 'Excelente',
    ], (new StoreRatingRequest())->rules());

    expect($invalid->errors()->has('score'))->toBeTrue();
    expect($valid->passes())->toBeTrue();
});

test('client register request requires unique email and id number', function () {
    clientUser(['email' => 'taken@example.com', 'id_number' => '1234567890']);

    $validator = Validator::make([
        'name' => 'Nuevo',
        'email' => 'taken@example.com',
        'phone' => '3001112233',
        'address' => 'Calle 1',
        'city' => 'Manizales',
        'type_id' => 'CC',
        'id_number' => '1234567890',
        'password' => 'password123',
    ], (new RegisterRequest())->rules());

    expect($validator->errors()->has('email'))->toBeTrue();
    expect($validator->errors()->has('id_number'))->toBeTrue();
});
