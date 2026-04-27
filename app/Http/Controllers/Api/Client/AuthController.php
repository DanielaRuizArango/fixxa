<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Http\Requests\Api\Client\RegisterRequest;
use App\Http\Requests\Api\Client\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Registro de un nuevo cliente.
     */
    public function register(RegisterRequest $request)
    {
        try {
            $validatedData = $request->validated();

            // Guardar imagen si fue enviada
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('users/images', 'public');
            }

            // Crear el usuario con rol de cliente
            $user = User::create([
                'name'      => $validatedData['name'],
                'email'     => $validatedData['email'],
                'password'  => Hash::make($validatedData['password']),
                'phone'     => $validatedData['phone'],
                'address'   => $validatedData['address'],
                'city'      => $validatedData['city'],
                'type_id'   => $validatedData['type_id'],
                'id_number' => $validatedData['id_number'],
                'image'     => $imagePath,
            ]);
            
            $user->assignRole('client');

            // Crear perfil de cliente (relación)
            Client::create([
                'user_id' => $user->id,
            ]);

            $token = $user->createToken('client_token')->plainTextToken;

            Log::info('Cliente registrado exitosamente', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Cliente registrado exitosamente.',
                'data'    => [
                    'user'         => $user->load('client'),
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en registro de cliente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data'  => $request->except(['password']),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar el cliente.',
            ], 500);
        }
    }

    /**
     * Login del cliente.
     */
    public function login(LoginRequest $request)
    {
        $validatedData = $request->validated();

        $user = User::where('email', $validatedData['email'])
                    ->role('client')
                    ->first();

        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            Log::warning('Intento de inicio de sesión fallido (Cliente)', ['email' => $validatedData['email']]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if ($user->status === 'blocked') {
            Log::warning('Intento de inicio de sesión de cliente bloqueado', ['user_id' => $user->id, 'email' => $user->email]);
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is blocked. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('client_token')->plainTextToken;

        Log::info('Inicio de sesión exitoso (Cliente)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Inicio de sesión exitoso.',
            'data'    => [
                'user'         => $user->load('client'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout del cliente (revocar token).
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        Log::info('Sesión cerrada (Cliente)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }
}

