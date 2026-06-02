<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technician;
use Illuminate\Http\Request;

class PublicTechnicianController extends Controller
{
    /**
     * Display the specified technician's profile for clients.
     */
    public function show($id)
    {
        $technician = Technician::with(['user', 'ratings.serviceCase.client.user', 'assets'])
            ->withAvg('ratings', 'score')
            ->withCount('ratings')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id' => $technician->id,
                'name' => $technician->user->name,
                'email' => $technician->user->email,
                'phone' => $technician->user->phone,
                'image' => $technician->user->image,
                'city' => $technician->user->city,
                'experience' => $technician->experience,
                'title' => $technician->title,
                'working_hours' => $technician->working_hours,
                'average_rating' => $technician->average_rating,
                'ratings' => $technician->ratings,
                'assets' => $technician->assets,
            ],
        ]);
    }
}
