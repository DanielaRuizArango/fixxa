<?php

namespace Database\Seeders;

use App\Models\TechnicianAsset;
use App\Models\User;
use App\Support\RejectionReasons;
use Illuminate\Database\Seeder;

class CertificationSeeder extends Seeder
{
    /**
     * URL de imagen por defecto para certificados / diplomas.
     */
    private string $defaultCertImage = 'https://imgv2-1-f.scribdassets.com/img/document/511686582/original/1045ed1900/1?v=1';

    /**
     * URL de imagen por defecto para cédulas de identidad.
     */
    private string $defaultIdImage = 'https://www.elpais.com.co/resizer/v2/4LID3CTAJ5EKLH2LKQPRNZYS2Y.png?auth=30992a0e2b6dbd6ff7efa7706210504c0f4d8325aa20dc8430cd3019933cfdad&smart=true&quality=75&width=1280&fitfill=false';

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

        $certCount = 0;
        $idCount   = 0;

        foreach ($technicians as $technician) {
            // ── 1. Certificaciones (1-3 por técnico) ──────────────────────────
            $count    = rand(1, 3);
            $selected = collect($this->certificationNames)->shuffle()->take($count);

            foreach ($selected as $description) {
                $status = $this->randomStatus();

                $data = [
                    'technician_id' => $technician->id,
                    'type'          => 'certification',
                    'image_path'    => $this->defaultCertImage,
                    'description'   => $description,
                    'status'        => $status,
                ];

                if ($status !== 'pending' && $reviewer) {
                    $data['reviewed_by'] = $reviewer->id;
                    $data['reviewed_at'] = now()->subDays(rand(1, 30));

                    if ($status === 'rejected') {
                        $reasons = RejectionReasons::forCertification();
                        $data['rejection_reason'] = $reasons[array_rand($reasons)];
                    }
                }

                TechnicianAsset::create($data);
                $certCount++;
            }

            // ── 2. Documento de identidad (cédula) — 1 por técnico ────────────
            $idStatus = $this->randomStatus();

            $idData = [
                'technician_id' => $technician->id,
                'type'          => 'id_document',
                'image_path'    => $this->defaultIdImage,
                'description'   => 'Cédula de ciudadanía',
                'status'        => $idStatus,
            ];

            if ($idStatus !== 'pending' && $reviewer) {
                $idData['reviewed_by'] = $reviewer->id;
                $idData['reviewed_at'] = now()->subDays(rand(1, 30));

                if ($idStatus === 'rejected') {
                    $reasons = RejectionReasons::forIdDocument();
                    $idData['rejection_reason'] = $reasons[array_rand($reasons)];
                }
            }

            TechnicianAsset::create($idData);
            $idCount++;
        }

        $this->command->info("CertificationSeeder: {$certCount} certificaciones y {$idCount} cédulas creadas para {$technicians->count()} técnicos.");
    }

    /**
     * Distribuye los estados: 50% aprobadas, 30% pendientes, 20% rechazadas.
     */
    private function randomStatus(): string
    {
        $rand = rand(1, 10);

        if ($rand <= 5) {
            return 'approved';
        } elseif ($rand <= 8) {
            return 'pending';
        }

        return 'rejected';
    }
}
