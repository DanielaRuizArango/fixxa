<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\Technician;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SystemAlertController extends Controller
{
    /**
     * Get critical system alerts for the admin panel.
     */
    public function index()
    {
        try {
            // 1. Cases without response for more than 24 hours
            $unansweredCases = ServiceCase::with(['client.user'])
                ->where('status', 'active')
                ->whereDoesntHave('responses')
                ->where('created_at', '<', Carbon::now()->subHours(24))
                ->get()
                ->map(function ($case) {
                    return [
                        'id' => $case->id,
                        'title' => $case->title,
                        'client_name' => $case->client->user->name ?? 'Unknown',
                        'created_at' => $case->created_at,
                        'hours_waiting' => (int) abs(Carbon::now()->diffInHours($case->created_at)),
                        'type' => 'unanswered_case',
                        'severity' => 'high'
                    ];
                });

            // 2. Technicians with poor ratings (Avg < 3.0 and at least 3 ratings)
            $poorTechnicians = Technician::with(['user'])
                ->withCount('ratings')
                ->withAvg('ratings', 'score')
                ->get()
                ->filter(fn ($tech) => $tech->ratings_count >= 3 && ($tech->ratings_avg_score ?? 0) < 3.0)
                ->values()
                ->map(function ($tech) {
                    return [
                        'id' => $tech->id,
                        'name' => $tech->user->name ?? 'Unknown',
                        'average_rating' => round($tech->ratings_avg_score, 1),
                        'total_ratings' => $tech->ratings_count,
                        'type' => 'poor_technician_rating',
                        'severity' => 'critical'
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'alerts' => [
                        'unanswered_cases' => $unansweredCases,
                        'poor_technicians' => $poorTechnicians
                    ],
                    'summary' => [
                        'total_critical_alerts' => count($unansweredCases) + count($poorTechnicians)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching system alerts: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch system alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
