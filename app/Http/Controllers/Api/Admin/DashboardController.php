<?php

namespace App\Http\Controllers\Api\Admin;

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
        $data = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_metrics', 300, function () {
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

        // 4. Resumen de logs de auditoría recientes con filtros de rol
        $user = auth()->user();
        $query = AuditLog::with('admin');

        if ($user && !$user->hasRole('super_admin')) {
            // Solo muestra los logs cuyos actores tengan rol de cliente o técnico (excluyendo administradores)
            $query->whereHas('admin', function ($q) {
                $q->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', ['client', 'technician']);
                });
            });
        }

        $recentLogs = $query->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // 5. Total de servicios completados (histórico)
        $completedServices = ServiceCase::where('status', 'resolved')->count();

            return [
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
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Get paginated audit logs for admin panel.
     */
    public function getLogs(Request $request)
    {
        $user = auth()->user();
        $query = AuditLog::with('admin');

        // Apply access control based on user role
        if ($user && !$user->hasRole('super_admin')) {
            // Non-super-admins can only see client and technician logs
            $query->whereHas('admin', function ($q) {
                $q->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', ['client', 'technician']);
                });
            });
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('admin', function ($adminQ) use ($search) {
                      $adminQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply action filter
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Apply date filter
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }
}
