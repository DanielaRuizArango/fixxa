<?php

use App\Models\Client;
use App\Models\ServiceCase;

test('client belongs to user and has many service cases', function () {
    $user = clientUser();
    $client = $user->client;

    serviceCaseFor($user, ['title' => 'Caso A']);
    serviceCaseFor($user, ['title' => 'Caso B']);

    expect($client->user->id)->toBe($user->id);
    expect($client->serviceCases)->toHaveCount(2);
    expect($client->serviceCases->first())->toBeInstanceOf(ServiceCase::class);
});
