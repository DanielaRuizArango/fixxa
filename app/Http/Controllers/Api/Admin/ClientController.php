<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Http\Requests\Api\Admin\StoreClientRequest;
use App\Http\Requests\Api\Admin\UpdateClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Utils\AuditLogger;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request)
    {
        try {
            $query = User::role('client')->with('client');

            // Búsqueda por nombre, email o número de identificación
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('id_number', 'like', "%{$search}%");
                });
            }

            // Filtro por ciudad
            if ($request->has('city') && $request->city != '') {
                $query->where('city', $request->city);
            }

            // Filtro por estado
            if ($request->has('status') && $request->status != '') {
                $query->where('status', $request->status);
            }

            $clients = $query->paginate(50);
            
            return response()->json([
                'status' => 'success',
                'data' => $clients
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing clients: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list clients'
            ], 500);
        }
    }

    /**
     * Display the specified client.
     */
    public function show($id)
    {
        try {
            $user = User::role('client')->with(['client.serviceCases'])->findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found'
            ], 404);
        }
    }

    /**
     * Store a newly created client.
     */
    public function store(StoreClientRequest $request)
    {
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

            $user->assignRole('client');

            Client::create(['user_id' => $user->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Client created successfully',
                'data' => $user->load('client')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating client: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create client'
            ], 500);
        }
    }

    /**
     * Update the specified client.
     */
    public function update(UpdateClientRequest $request, $id)
    {
        try {
            $user = User::role('client')->findOrFail($id);

            if ($request->hasFile('image')) {
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->image = $request->file('image')->store('users/images', 'public');
            }

            $user->update($request->only(['name', 'email', 'phone', 'city', 'address', 'type_id', 'id_number', 'status']));

            return response()->json([
                'status' => 'success',
                'message' => 'Client updated successfully',
                'data' => $user->load('client')
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating client: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update client'
            ], 500);
        }
    }

    /**
     * Block/unblock a client.
     */
    public function block($id)
    {
        try {
            $user = User::role('client')->findOrFail($id);
            
            $oldStatus = $user->status;
            $user->status = ($user->status === 'active') ? 'blocked' : 'active';
            $user->save();

            AuditLogger::log(
                ($user->status === 'blocked' ? 'block_client' : 'unblock_client'),
                'User',
                $user->id,
                ($user->status === 'blocked' ? "Bloqueó al cliente {$user->name}" : "Desbloqueó al cliente {$user->name}"),
                ['status' => $oldStatus],
                ['status' => $user->status]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Client status updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Error blocking/unblocking client: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update client status'
            ], 500);
        }
    }

    /**
     * Remove the specified client.
     */
    public function destroy($id)
    {
        try {
            $user = User::role('client')->findOrFail($id);
            
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $user->delete();

            AuditLogger::log(
                'delete_client',
                'User',
                $id,
                "Eliminó permanentemente al cliente {$user->name}",
                ['name' => $user->name, 'email' => $user->email]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Client deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting client: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete client'
            ], 500);
        }
    }
}
