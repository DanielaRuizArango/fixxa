<?php

use App\Models\User;
use App\Models\Client;
use Database\Seeders\ClientSeeder;

it('can create a client using factory', function () {
    $user = User::factory()->client()->create();

    expect($user->hasRole('client'))->toBeTrue();
    expect(Client::where('user_id', $user->id)->exists())->toBeTrue();
});

it('seeds clients correctly from seeder', function () {
    $this->seed(ClientSeeder::class);

    $this->assertDatabaseHas('users', ['email' => 'cliente@fixxa.com']);
    
    $client = User::where('email', 'cliente@fixxa.com')->first();
    expect($client->hasRole('client'))->toBeTrue();
});
