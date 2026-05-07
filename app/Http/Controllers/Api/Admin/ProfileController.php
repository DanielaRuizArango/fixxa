<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateAdminProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Display the admin's profile.
     */
    public function show(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()->load('admin')
        ]);
    }

    /**
     * Update the admin's profile.
     */
    public function update(UpdateAdminProfileRequest $request)
    {
        $user = $request->user();

        try {
            if ($request->hasFile('image')) {
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $user->image = $request->file('image')->store('users/images', 'public');
            }

            $user->name = $request->input('name', $user->name);
            $user->email = $request->input('email', $user->email);
            $user->phone = $request->input('phone', $user->phone);
            $user->city = $request->input('city', $user->city);
            $user->address = $request->input('address', $user->address);

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => $user->load('admin')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating admin profile: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile'
            ], 500);
        }
    }
}
