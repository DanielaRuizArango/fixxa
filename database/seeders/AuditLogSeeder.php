<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Seed dummy audit logs for testing.
     */
    public function run(): void
    {
        $admin = User::role(['super_admin', 'admin'])->first();
        if (!$admin) {
            return;
        }

        $logs = [
            [
                'actor_id'    => $admin->id,
                'action'      => 'block_user',
                'target_type' => 'App\\Models\\User',
                'target_id'   => 5,
                'description' => 'Bloqueó al técnico Juan Pérez por inactividad.',
                'ip_address'  => '127.0.0.1',
                'created_at'  => now()->subHours(2),
            ],
            [
                'actor_id'    => $admin->id,
                'action'      => 'approve_certification',
                'target_type' => 'App\\Models\\TechnicianAsset',
                'target_id'   => 1,
                'description' => 'Aprobó el certificado de Electricista de Carlos Mendoza.',
                'ip_address'  => '127.0.0.1',
                'created_at'  => now()->subHours(5),
            ],
            [
                'actor_id'    => $admin->id,
                'action'      => 'unblock_user',
                'target_type' => 'App\\Models\\User',
                'target_id'   => 6,
                'description' => 'Desbloqueó al cliente María Gómez tras verificar identidad.',
                'ip_address'  => '127.0.0.1',
                'created_at'  => now()->subDays(1),
            ],
            [
                'actor_id'    => $admin->id,
                'action'      => 'delete_case',
                'target_type' => 'App\\Models\\ServiceCase',
                'target_id'   => 12,
                'description' => 'Eliminó el caso #12 por duplicidad y contenido erróneo.',
                'ip_address'  => '127.0.0.1',
                'created_at'  => now()->subDays(2),
            ],
        ];

        foreach ($logs as $log) {
            AuditLog::create($log);
        }
    }
}
