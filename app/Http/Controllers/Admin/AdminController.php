<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Display a listing of admin users.
     */
    public function index()
    {
        try {
            $admins = User::where('role', 'admin')->with('admin')->get();
            return response()->json([
                'status' => 'success',
                'data' => $admins
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing admins: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list admin users'
            ], 500);
        }
    }

    /**
     * Store a newly created admin user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'type_id' => 'nullable|string|max:20',
            'id_number' => 'nullable|string|max:20|unique:users',
            'image' => 'nullable|image|max:2048',
            'spatie_role' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

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
                'role' => 'admin',
                'phone' => $request->phone,
                'city' => $request->city,
                'address' => $request->address,
                'type_id' => $request->type_id,
                'id_number' => $request->id_number,
                'image' => $imagePath,
                'status' => 'active',
            ]);

            // Assign Spatie role
            if ($request->spatie_role) {
                $user->assignRole($request->spatie_role);
            } else {
                $user->assignRole('admin');
            }

            Admin::create(['user_id' => $user->id]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin user created successfully',
                'data' => $user->load('admin')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create admin user'
            ], 500);
        }
    }

    /**
     * Update the specified admin user.
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::where('role', 'admin')->findOrFail($id);

            // Avoid self-lock or self-status-change if necessary
            // Or maybe allow it for certain admins. For now, just allow admin management.

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
                'message' => 'Admin user updated successfully',
                'data' => $user->load('admin')
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update admin user'
            ], 500);
        }
    }

    /**
     * Block/unblock an admin user.
     */
    public function block($id)
    {
        try {
            $adminToBlock = User::where('role', 'admin')->findOrFail($id);
            
            // Avoid blocking yourself
            if (auth()->id() == $adminToBlock->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot block your own account.'
                ], 403);
            }

            $adminToBlock->status = ($adminToBlock->status === 'active') ? 'blocked' : 'active';
            $adminToBlock->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin status updated successfully',
                'data' => $adminToBlock
            ]);

        } catch (\Exception $e) {
            Log::error('Error blocking admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update admin status'
            ], 500);
        }
    }

    /**
     * Remove the specified admin user.
     */
    public function destroy($id)
    {
        try {
            $adminToDelete = User::where('role', 'admin')->findOrFail($id);
            
            // Avoid deleting yourself
            if (auth()->id() == $adminToDelete->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot delete your own account.'
                ], 403);
            }

            if ($adminToDelete->image) {
                Storage::disk('public')->delete($adminToDelete->image);
            }

            $adminToDelete->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin user deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete admin user'
            ], 500);
        }
    }
}
