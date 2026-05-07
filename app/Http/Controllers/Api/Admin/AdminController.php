<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Admin;
use App\Http\Requests\Api\Admin\StoreAdminRequest;
use App\Http\Requests\Api\Admin\UpdateAdminRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            $admins = User::role(['super_admin', 'admin', 'moderator'])->with('admin')->paginate(10);
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
    public function store(StoreAdminRequest $request)
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
     * Display the specified admin user.
     */
    public function show($id)
    {
        try {
            $admin = User::role(['super_admin', 'admin', 'moderator'])->with('admin')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $admin
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing admin: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Admin user not found'
            ], 404);
        }
    }

    /**
     * Update the specified admin user.
     */
    public function update(UpdateAdminRequest $request, $id)
    {
        try {
            $user = User::role(['super_admin', 'admin', 'moderator'])->findOrFail($id);

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
            $adminToBlock = User::role(['super_admin', 'admin', 'moderator'])->findOrFail($id);
            
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
            $adminToDelete = User::role(['super_admin', 'admin', 'moderator'])->findOrFail($id);
            
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
