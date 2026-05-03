<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    /**
     * Display a listing of all ratings.
     */
    public function index(Request $request)
    {
        try {
            $query = Rating::with(['client.user', 'technician.user', 'serviceCase']);

            // Búsqueda por nombre de cliente, técnico o título de caso
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->whereHas('client.user', function($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%");
                    })->orWhereHas('technician.user', function($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%");
                    })->orWhereHas('serviceCase', function($caseQ) use ($search) {
                        $caseQ->where('title', 'like', "%{$search}%");
                    });
                });
            }

            // Filtro por puntuación
            if ($request->has('score') && $request->score != '') {
                $query->where('score', $request->score);
            }

            $ratings = $query->latest()->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $ratings
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing ratings for admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list ratings'
            ], 500);
        }
    }

    /**
     * Remove a rating.
     */
    public function destroy($id)
    {
        try {
            $rating = Rating::findOrFail($id);
            $rating->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Rating deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting rating for admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete rating'
            ], 500);
        }
    }
}
