<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('client');

        return response()->json([
            'status' => 'success',
            'data'   => $user,
        ]);
    }

    /**
     * Update the authenticated user's profile in storage.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validatedData = $request->validated();

        // Actualizar imagen si fue enviada
        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $validatedData['image'] = $request->file('image')->store('users/images', 'public');
        }

        // Si se envía contraseña, actualizarla (Opcional, no está en las reglas básicas pero es buena práctica)
        if ($request->filled('password')) {
            $validatedData['password'] = Hash::make($request->password);
        } else {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json([
            'status'  => 'success',
            'message' => 'Perfil actualizado exitosamente.',
            'data'    => $user->load('client'),
        ]);
    }
}
