<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            $clients = $query->paginate(10);
            
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
     * Store a newly created client.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'city' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'type_id' => 'required|string|max:20',
            'id_number' => 'required|string|max:20|unique:users',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

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
    public function update(Request $request, $id)
    {
        try {
            $user = User::role('client')->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string|max:20',
                'city' => 'sometimes|string|max:50',
                'address' => 'sometimes|string|max:255',
                'type_id' => 'sometimes|string|max:20',
                'id_number' => 'sometimes|string|max:20|unique:users,id_number,' . $user->id,
                'image' => 'nullable|image|max:2048',
                'status' => 'sometimes|in:active,blocked',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

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
            
            $user->status = ($user->status === 'active') ? 'blocked' : 'active';
            $user->save();

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
