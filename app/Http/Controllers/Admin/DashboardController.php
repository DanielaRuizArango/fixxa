<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\Technician;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get quick metrics for the admin dashboard.
     */
    public function getMetrics()
    {
        // 1. Casos Activos vs Cerrados
        $casesCount = ServiceCase::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $activeCases = ($casesCount['active'] ?? 0) + ($casesCount['responded'] ?? 0) + ($casesCount['pending'] ?? 0);
        $closedCases = ($casesCount['resolved'] ?? 0) + ($casesCount['cancelled'] ?? 0);

        // 2. Técnicos Disponibles
        $availableTechnicians = Technician::where('is_available', true)->count();
        $totalTechnicians = Technician::count();

        // 3. Clientes registrados recientemente (últimos 30 días)
        $recentClients = User::role('client')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'name', 'email', 'created_at']);

        // 4. Resumen de logs de auditoría recientes
        $recentLogs = AuditLog::with('admin')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // 5. Total de servicios completados (histórico)
        $completedServices = ServiceCase::where('status', 'resolved')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'cases' => [
                    'active' => $activeCases,
                    'closed' => $closedCases,
                    'total'  => ServiceCase::count(),
                ],
                'technicians' => [
                    'available' => $availableTechnicians,
                    'total'     => $totalTechnicians,
                ],
                'recent_clients' => $recentClients,
                'recent_logs'    => $recentLogs,
                'completed_services' => $completedServices,
                'total_users' => User::count(),
            ]
        ]);
    }
}
