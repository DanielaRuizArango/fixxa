<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Technician;
use App\Http\Requests\Api\Admin\StoreTechnicianRequest;
use App\Http\Requests\Api\Admin\UpdateTechnicianRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Utils\AuditLogger;

class TechnicianController extends Controller
{
    /**
     * Display a listing of technicians.
     */
    public function index(Request $request)
    {
        try {
            $query = Technician::with([
                'user',
                'ratings',
                'assets' => fn ($q) => $q->whereIn('type', ['id_document', 'certification']),
            ])->withAvg('ratings', 'score')->withCount('ratings');

            // Búsqueda por nombre, email o número de identificación del usuario asociado
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('id_number', 'like', "%{$search}%");
                });
            }

            // Filtro por ciudad del usuario
            if ($request->has('city') && $request->city != '') {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('city', $request->city);
                });
            }

            // Filtro por estado del usuario
            if ($request->has('status') && $request->status != '') {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }

            $technicians = $query->paginate(50);
            
            return response()->json([
                'status' => 'success',
                'data' => $technicians
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing technicians: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list technicians'
            ], 500);
        }
    }

    /**
     * Display the specified technician.
     */
    public function show($id)
    {
        try {
            $technician = Technician::with([
                'user',
                'ratings.client.user',
                'caseResponses.serviceCase',
                'assets' => fn ($q) => $q->whereIn('type', ['id_document', 'certification']),
            ])->findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => $technician
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Technician not found'
            ], 404);
        }
    }

    /**
     * Store a newly created technician.
     */
    public function store(StoreTechnicianRequest $request)
    {
        DB::beginTransaction();
        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('users/images', 'public');
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'city' => $request->city,
                'address' => $request->address,
                'type_id' => $request->type_id,
                'id_number' => $request->id_number,
                'image' => $imagePath,
                'status' => 'active',
            ]);

            $user->assignRole('technician');

            Technician::create([
                'user_id' => $user->id,
                'experience' => $request->experience,
                'title' => $request->title,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Technician created successfully',
                'data' => $user->load('technician')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating technician: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create technician'
            ], 500);
        }
    }

    /**
     * Update the specified technician.
     */
    public function update(UpdateTechnicianRequest $request, $id)
    {
        try {
            $user = User::role('technician')->findOrFail($id);

            DB::beginTransaction();

            if ($request->hasFile('image')) {
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->image = $request->file('image')->store('users/images', 'public');
            }

            $user->update($request->only(['name', 'email', 'phone', 'city', 'address', 'type_id', 'id_number', 'status']));

            if ($request->has('experience') || $request->has('title')) {
                $user->technician->update($request->only(['experience', 'title']));
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Technician updated successfully',
                'data' => $user->load('technician')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating technician: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update technician'
            ], 500);
        }
    }

    /**
     * Block/unblock a technician.
     */
    public function block($id)
    {
        try {
            $user = User::role('technician')->findOrFail($id);
            
            $oldStatus = $user->status;
            $user->status = ($user->status === 'active') ? 'blocked' : 'active';
            $user->save();

            AuditLogger::log(
                ($user->status === 'blocked' ? 'block_technician' : 'unblock_technician'),
                'User',
                $user->id,
                ($user->status === 'blocked' ? "Bloqueó al técnico {$user->name}" : "Desbloqueó al técnico {$user->name}"),
                ['status' => $oldStatus],
                ['status' => $user->status]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Technician status updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Error blocking/unblocking technician: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update technician status'
            ], 500);
        }
    }

    /**
     * Remove the specified technician.
     */
    public function destroy($id)
    {
        try {
            $user = User::role('technician')->findOrFail($id);
            
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $user->delete();

            AuditLogger::log(
                'delete_technician',
                'User',
                $id,
                "Eliminó permanentemente al técnico {$user->name}",
                ['name' => $user->name, 'email' => $user->email]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Technician deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting technician: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete technician'
            ], 500);
        }
    }
}
