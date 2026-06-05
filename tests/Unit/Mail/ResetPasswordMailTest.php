<?php

use App\Mail\ResetPasswordMail;

test('reset password mail has correct envelope and content', function () {
    $user = clientUser(['email' => 'mail.test@example.com']);
    $resetUrl = 'http://localhost:3000/reset-password?token=abc123';

    $mailable = new ResetPasswordMail($user, $resetUrl);

    $mailable->assertHasSubject('Restablecimiento de Contraseña - ' . config('app.name'));
    $mailable->assertSeeInHtml($user->name);
    $mailable->assertSeeInHtml($resetUrl);
});
