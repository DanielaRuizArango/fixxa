<?php

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Seeder was already run in tests/Pest.php -> beforeEach
});

it('registers a new client successfully', function () {
    Storage::fake('public');

    $response = $this->postJson('/api/client/register', [
        'name' => 'Jane Client',
        'email' => 'client@example.com',
        'phone' => '0987654321',
        'address' => '456 Client Avenue',
        'city' => 'Client City',
        'type_id' => 'CC',
        'id_number' => '5040302010',
        'password' => 'password123',
        'image' => UploadedFile::fake()->image('profile.jpg'),
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Cliente registrado exitosamente.',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'client@example.com',
        'name' => 'Jane Client',
        'id_number' => '5040302010',
    ]);

    $user = User::where('email', 'client@example.com')->first();
    
    $this->assertTrue($user->hasRole('client'));

    $this->assertDatabaseHas('clients', [
        'user_id' => $user->id,
    ]);
});

it('fails to register client with existing email', function () {
    User::factory()->create([
        'email' => 'existing_client@example.com'
    ]);

    $response = $this->postJson('/api/client/register', [
        'name' => 'Jane Client',
        'email' => 'existing_client@example.com',
        'phone' => '0987654321',
        'address' => '456 Client Avenue',
        'city' => 'Client City',
        'type_id' => 'CC',
        'id_number' => '5040302010',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs in an active client successfully', function () {
    $user = User::factory()->create([
        'email' => 'active_client@example.com',
        'password' => Hash::make('password123'),
        'status' => 'active'
    ]);
    $user->assignRole('client');
    
    Client::create([
        'user_id' => $user->id,
    ]);

    $response = $this->postJson('/api/client/login', [
        'email' => 'active_client@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'user',
                'access_token',
                'token_type',
            ]
        ]);
});

it('fails to login client with incorrect password', function () {
    $user = User::factory()->create([
        'email' => 'wrong_client_pass@example.com',
        'password' => Hash::make('password123'),
        'status' => 'active'
    ]);
    $user->assignRole('client');

    $response = $this->postJson('/api/client/login', [
        'email' => 'wrong_client_pass@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'message' => 'Credenciales incorrectas.',
        ]);
});

it('fails to login when client is inactive', function () {
    $user = User::factory()->create([
        'email' => 'inactive_client@example.com',
        'password' => Hash::make('password123'),
        'status' => 'inactive'
    ]);
    $user->assignRole('client');

    $response = $this->postJson('/api/client/login', [
        'email' => 'inactive_client@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Tu cuenta no está activa. Por favor, contacta al soporte.',
        ]);
});

it('handles internal errors during client registration', function () {
    Storage::fake('public');

    Event::listen('eloquent.creating: '.User::class, function () {
        throw new \Exception('Registration failed');
    });

    $this->postJson('/api/client/register', [
        'name' => 'Jane Client',
        'email' => 'error.client@example.com',
        'phone' => '0987654321',
        'address' => '456 Client Avenue',
        'city' => 'Client City',
        'type_id' => 'CC',
        'id_number' => '5040302099',
        'password' => 'password123',
    ])
        ->assertStatus(500)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Error al registrar el cliente.');
});

it('logs out a client successfully', function () {
    $user = User::factory()->create();
    $user->assignRole('client');
    
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/client/logout');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
});
