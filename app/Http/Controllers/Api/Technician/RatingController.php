<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rating;

class RatingController extends Controller
{
    /**
     * Display a listing of ratings for the authenticated technician.
     */
    public function index(Request $request)
    {
        $technician = $request->user()->technician;
        
        if (!$technician) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes un perfil de técnico asociado.'
            ], 403);
        }

        $query = Rating::where('technician_id', $technician->id)
            ->with(['client.user', 'serviceCase']);

        // Filtro por puntuación
        if ($request->filled('score')) {
            $query->where('score', $request->score);
        }

        // Búsqueda por nombre de cliente o título de caso
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('client.user', function($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%");
                })->orWhereHas('serviceCase', function($caseQ) use ($search) {
                    $caseQ->where('title', 'like', "%{$search}%");
                });
            });
        }

        $ratings = $query->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => [
                'average_score' => round($technician->ratings()->avg('score'), 1),
                'total_ratings' => $technician->ratings()->count(),
                'ratings'       => $ratings,
            ],
        ]);
    }
}
