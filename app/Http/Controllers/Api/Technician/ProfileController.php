<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Technician\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Display the authenticated technician's profile.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load(['technician.assets']);

        return response()->json([
            'status' => 'success',
            'data'   => $user,
        ]);
    }

    /**
     * Update the authenticated technician's profile in storage.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validatedData = $request->validated();

        // Actualizar datos del usuario
        $userData = [
            'name'    => $validatedData['name'],
            'email'   => $validatedData['email'],
            'phone'   => $validatedData['phone'],
            'address' => $validatedData['address'],
            'city'    => $validatedData['city'],
        ];

        // Actualizar imagen si fue enviada
        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $userData['image'] = $request->file('image')->store('users/images', 'public');
        }

        // Si se envía contraseña, actualizarla
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Actualizar datos de perfil del técnico
        $technician = $user->technician;
        $technician->update([
            'experience'   => $validatedData['experience'],
            'title'         => $validatedData['title'],
            'is_available'  => $request->has('is_available') ? (bool)$validatedData['is_available'] : $technician->is_available,
            'working_hours' => $validatedData['working_hours'] ?? $technician->working_hours,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Perfil de técnico actualizado exitosamente.',
            'data'    => $user->load('technician'),
        ]);
    }
}
