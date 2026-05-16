<?php

namespace Database\Seeders;

use App\Models\TechnicianAsset;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificationSeeder extends Seeder
{
    /**
     * Nombres de certificaciones realistas para técnicos de servicios del hogar.
     */
    private array $certificationNames = [
        'Certificación en Refrigeración y Aire Acondicionado (INEN)',
        'Técnico Certificado en Instalaciones Eléctricas Residenciales',
        'Certificado de Competencias en Plomería y Fontanería',
        'Certificación SENA - Mantenimiento de Electrodomésticos',
        'Curso Avanzado de Soldadura MIG/TIG',
        'Certificación en Seguridad Eléctrica (RETIE)',
        'Técnico en Sistemas de Calefacción y Ventilación',
        'Certificado en Mantenimiento de Redes Hidrosanitarias',
        'Curso de Gas Natural Domiciliario y Seguridad',
        'Certificación en Pintura y Acabados de Interiores',
        'Técnico en Carpintería y Ebanistería',
        'Certificado en Climatización y Sistemas HVAC',
        'Curso de Instalación de Paneles Solares Fotovoltaicos',
        'Certificación en Cámaras de Seguridad y CCTV',
        'Técnico en Automatización del Hogar (Domótica)',
    ];

    private array $rejectionReasons = [
        'El documento no es legible. Por favor sube una imagen con mayor resolución.',
        'La certificación está vencida. Sube un certificado vigente.',
        'La entidad emisora no está registrada en nuestro sistema de validación.',
        'El nombre en el certificado no coincide con el nombre del perfil.',
        'La imagen no corresponde a un certificado oficial reconocido.',
    ];

    public function run(): void
    {
        // Obtener el admin para asignarlo como revisor
        $reviewer = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->first();

        // Obtener todos los técnicos con sus perfiles
        $technicians = \App\Models\Technician::with('user')->get();

        if ($technicians->isEmpty()) {
            $this->command->warn('No se encontraron técnicos. Ejecuta TechnicianSeeder primero.');
            return;
        }

        $created = 0;

        foreach ($technicians as $technician) {
            // Cada técnico recibe entre 1 y 3 certificaciones
            $count = rand(1, 3);

            // Seleccionar certificaciones aleatorias sin repetir
            $selected = collect($this->certificationNames)->shuffle()->take($count);

            foreach ($selected as $description) {
                // Distribuir los estados: 50% aprobadas, 30% pendientes, 20% rechazadas
                $rand = rand(1, 10);
                if ($rand <= 5) {
                    $status = 'approved';
                } elseif ($rand <= 8) {
                    $status = 'pending';
                } else {
                    $status = 'rejected';
                }

                $data = [
                    'technician_id' => $technician->id,
                    'type'          => 'certification',
                    'image_path'    => 'technicians/assets/sample_cert_' . rand(1, 5) . '.jpg',
                    'description'   => $description,
                    'status'        => $status,
                ];

                // Si fue revisada, agregar datos del revisor
                if ($status !== 'pending' && $reviewer) {
                    $data['reviewed_by'] = $reviewer->id;
                    $data['reviewed_at'] = now()->subDays(rand(1, 30));

                    if ($status === 'rejected') {
                        $data['rejection_reason'] = $this->rejectionReasons[array_rand($this->rejectionReasons)];
                    }
                }

                TechnicianAsset::create($data);
                $created++;
            }
        }

        $this->command->info("CertificationSeeder: {$created} certificaciones creadas para {$technicians->count()} técnicos.");
    }
}
