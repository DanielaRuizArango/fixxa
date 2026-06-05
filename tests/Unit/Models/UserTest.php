<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Technician;
use App\Models\User;

test('user has profile relations for each role', function () {
    $client = clientUser();
    $technician = technicianUser();
    $admin = adminUser();

    expect($client->client)->toBeInstanceOf(Client::class);
    expect($technician->technician)->toBeInstanceOf(Technician::class);
    expect($admin->admin)->toBeInstanceOf(Admin::class);
});

test('user is online attribute follows project rules', function () {
    $firstUser = User::factory()->create(['email' => 'user1@example.com']);
    $technicianEmail = User::factory()->create(['email' => 'tecnico@fixxa.com']);
    $thirdUser = User::factory()->create(['email' => 'offline@example.com']);

    expect($firstUser->id)->toBe(1);
    expect($firstUser->is_online)->toBeTrue();
    expect($technicianEmail->is_online)->toBeTrue();
    expect($thirdUser->id % 3)->toBe(0);
    expect($thirdUser->is_online)->toBeFalse();
});

test('user uses soft deletes', function () {
    $user = clientUser();
    $user->delete();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('user password is hashed when assigned', function () {
    $user = User::factory()->create([
        'password' => 'plain-password',
    ]);

    expect($user->password)->not->toBe('plain-password');
    expect(\Illuminate\Support\Facades\Hash::check('plain-password', $user->password))->toBeTrue();
});
