<?php

use App\Models\User;
use App\Models\Technician;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Seeder was already run in tests/Pest.php -> beforeEach, 
    // but just to be sure we have the role, we will assume it's seeded.
});

it('registers a new technician successfully', function () {
    Storage::fake('public');

    $response = $this->postJson('/api/technician/register', [
        'name' => 'John Technician',
        'email' => 'tech@example.com',
        'phone' => '1234567890',
        'address' => '123 Tech Street',
        'city' => 'Tech City',
        'type_id' => 'CC',
        'id_number' => '1020304050',
        'experience' => '5 years of experience in repair',
        'title' => 'Senior Repair Technician',
        'password' => 'password123',
        'image' => UploadedFile::fake()->image('profile.jpg'),
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Técnico registrado exitosamente.',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'tech@example.com',
        'name' => 'John Technician',
        'id_number' => '1020304050',
    ]);

    $user = User::where('email', 'tech@example.com')->first();
    
    $this->assertTrue($user->hasRole('technician'));

    $this->assertDatabaseHas('technicians', [
        'user_id' => $user->id,
        'title' => 'Senior Repair Technician',
        'experience' => '5 years of experience in repair',
    ]);
});

it('fails to register with existing email', function () {
    User::factory()->create([
        'email' => 'existing@example.com'
    ]);

    $response = $this->postJson('/api/technician/register', [
        'name' => 'John Technician',
        'email' => 'existing@example.com',
        'phone' => '1234567890',
        'address' => '123 Tech Street',
        'city' => 'Tech City',
        'type_id' => 'CC',
        'id_number' => '1020304050',
        'experience' => '5 years',
        'title' => 'Tech',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs in an active technician successfully', function () {
    $user = User::factory()->create([
        'email' => 'active_tech@example.com',
        'password' => Hash::make('password123'),
        'status' => 'active'
    ]);
    $user->assignRole('technician');
    
    Technician::create([
        'user_id' => $user->id,
        'title' => 'Fixer',
        'experience' => '1 year',
    ]);

    $response = $this->postJson('/api/technician/login', [
        'email' => 'active_tech@example.com',
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

it('fails to login with incorrect password', function () {
    $user = User::factory()->create([
        'email' => 'wrong_pass@example.com',
        'password' => Hash::make('password123'),
        'status' => 'active'
    ]);
    $user->assignRole('technician');

    $response = $this->postJson('/api/technician/login', [
        'email' => 'wrong_pass@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'status' => 'error',
            'message' => 'Credenciales incorrectas.',
        ]);
});

it('fails to login when technician is inactive', function () {
    $user = User::factory()->create([
        'email' => 'inactive_tech@example.com',
        'password' => Hash::make('password123'),
        'status' => 'inactive'
    ]);
    $user->assignRole('technician');

    $response = $this->postJson('/api/technician/login', [
        'email' => 'inactive_tech@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Tu cuenta no está activa. Por favor, contacta al soporte.',
        ]);
});

it('logs out a technician successfully', function () {
    $user = User::factory()->create();
    $user->assignRole('technician');
    
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/technician/logout');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
});
