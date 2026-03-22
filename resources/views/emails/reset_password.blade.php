<x-mail::message>
# Restablecimiento de Contraseña

Hola {{ $user->name }},

Has recibido este correo porque hemos recibido una solicitud de restablecimiento de contraseña para tu cuenta.

<x-mail::button :url="$resetUrl">
Restablecer Contraseña
</x-mail::button>

Este enlace de restablecimiento de contraseña expirará en 60 minutos.

Si no has solicitado un restablecimiento de contraseña, no es necesario realizar ninguna otra acción.

Saludos,<br>
{{ config('app.name') }}
</x-mail::message>
