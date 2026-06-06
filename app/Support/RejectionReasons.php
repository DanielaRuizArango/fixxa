<?php

namespace App\Support;

class RejectionReasons
{
    public static function forCertification(): array
    {
        return [
            'El documento no es legible. Por favor sube una imagen con mayor resolución.',
            'La certificación está vencida. Sube un certificado vigente.',
            'La entidad emisora no está registrada en nuestro sistema de validación.',
            'El nombre en el certificado no coincide con el nombre del perfil.',
            'La imagen no corresponde a un certificado oficial reconocido.',
        ];
    }

    public static function forIdDocument(): array
    {
        return [
            'La cédula no es legible. Por favor sube una imagen con mayor resolución.',
            'El documento está vencido o deteriorado.',
            'Los datos de la cédula no coinciden con la información del perfil.',
            'La imagen no corresponde a una cédula de ciudadanía válida.',
            'La cédula presentada es una fotocopia no aceptada. Se requiere el documento original.',
        ];
    }
}
