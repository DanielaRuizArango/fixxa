<?php

use App\Models\User;
use App\Models\Admin;
use Database\Seeders\AdminSeeder;

it('can create an admin using factory', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasRole('admin'))->toBeTrue();
    expect(Admin::where('user_id', $user->id)->exists())->toBeTrue();
});

it('seeds administrative users correctly', function () {
    $this->seed(AdminSeeder::class);

    $this->assertDatabaseHas('users', ['email' => 'superadmin@fixxa.com']);
    $this->assertDatabaseHas('users', ['email' => 'admin@fixxa.com']);
    $this->assertDatabaseHas('users', ['email' => 'moderator@fixxa.com']);

    $superAdmin = User::where('email', 'superadmin@fixxa.com')->first();
    expect($superAdmin->hasRole('super_admin'))->toBeTrue();
});
