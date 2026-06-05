<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
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

it('updates the admin profile password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);
    $user->assignRole('admin');

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/admin/profile', [
        'name' => $user->name,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('replaces the admin profile image and deletes the previous one', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/images/old-admin.jpg', 'old-image');

    $user = User::factory()->create(['image' => 'users/images/old-admin.jpg']);
    $user->assignRole('admin');

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/admin/profile', [
        'name' => $user->name,
        'image' => UploadedFile::fake()->image('new-admin.jpg'),
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $user->refresh();
    expect($user->image)->not->toBe('users/images/old-admin.jpg');
    Storage::disk('public')->assertMissing('users/images/old-admin.jpg');
    Storage::disk('public')->assertExists($user->image);
});

it('returns 500 when admin profile update fails', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user, ['*']);

    Event::listen('eloquent.updating: '.User::class, function () {
        Event::forget('eloquent.updating: '.User::class);
        throw new \Exception('Update failed');
    });

    $this->postJson('/api/admin/profile', [
        'name' => 'Nombre Actualizado',
    ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to update profile');
});

it('blocks guest users from updating the admin profile', function () {
    $this->postJson('/api/admin/profile', ['name' => 'Guest'])->assertUnauthorized();
});
