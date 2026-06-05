<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('forgot password sends reset password mail', function () {
    Mail::fake();

    $user = clientUser(['email' => 'reset.client@example.com']);

    $response = $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user) {
        return $mail->user->id === $user->id
            && str_contains($mail->resetUrl, 'reset-password?token=')
            && str_contains($mail->resetUrl, 'email=');
    });

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

test('forgot password requires existing email', function () {
    Mail::fake();

    $this->postJson('/api/forgot-password', [
        'email' => 'noexiste@example.com',
    ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');

    Mail::assertNothingSent();
});

test('reset password updates user password with valid token', function () {
    Mail::fake();

    $user = clientUser(['email' => 'reset.flow@example.com']);

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $token = DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->value('token');

    $response = $this->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('reset password rejects invalid token', function () {
    $user = clientUser(['email' => 'invalid.token@example.com']);

    $this->postJson('/api/reset-password', [
        'email' => $user->email,
        'token' => 'token-invalido',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertStatus(400)
        ->assertJsonPath('status', 'error');
});

test('general login returns user role and token', function () {
    $user = clientUser([
        'email' => 'login.general@example.com',
        'password' => Hash::make('password123'),
        'status' => 'active',
    ]);

    $this->postJson('/api/login', [
        'email' => 'login.general@example.com',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.role', 'client')
        ->assertJsonStructure(['data' => ['access_token', 'user']]);
});
