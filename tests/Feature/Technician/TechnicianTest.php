<?php

use App\Models\User;
use App\Models\Technician;
use Database\Seeders\TechnicianSeeder;

it('can create a technician using factory', function () {
    $user = User::factory()->technician()->create();

    expect($user->hasRole('technician'))->toBeTrue();
    expect(Technician::where('user_id', $user->id)->exists())->toBeTrue();
});

it('seeds technicians correctly from seeder', function () {
    $this->seed(TechnicianSeeder::class);

    $this->assertDatabaseHas('users', ['email' => 'tecnico@fixxa.com']);

    $user = User::where('email', 'tecnico@fixxa.com')->first();
    expect($user->hasRole('technician'))->toBeTrue();
});
