<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the admin profile to an authenticated admin', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/admin/me');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
            ]
        ]);
});

it('blocks non-admin users from viewing the admin profile', function () {
    $user = User::factory()->create();
    $user->assignRole('client'); // Not an admin

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/admin/me');

    $response->assertStatus(403);
});

it('updates the admin profile successfully', function () {
    Storage::fake('public');
    
    $user = User::factory()->create([
        'name' => 'Original Admin',
        'email' => 'admin_test@example.com',
    ]);
    $user->assignRole('admin');

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/admin/profile', [
        'name' => 'Updated Admin',
        'email' => 'admin_updated@example.com',
        'phone' => '1122334455',
        'city' => 'Metropolis',
        'address' => '456 Admin Plaza',
        'image' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Profile updated successfully',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Admin',
        'email' => 'admin_updated@example.com',
        'phone' => '1122334455',
        'city' => 'Metropolis',
        'address' => '456 Admin Plaza',
    ]);
});

it('blocks guest users from viewing the admin profile', function () {
    $response = $this->getJson('/api/admin/me');

    $response->assertStatus(401);
});
