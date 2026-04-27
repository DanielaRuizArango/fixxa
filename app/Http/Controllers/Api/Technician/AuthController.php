<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Technician;
use App\Http\Requests\Api\Technician\RegisterRequest;
use App\Http\Requests\Api\Technician\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Registro de un nuevo técnico.
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

            // Crear el usuario con rol de técnico
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
            
            $user->assignRole('technician');

            // Crear perfil de técnico (relación)
            Technician::create([
                'user_id'    => $user->id,
                'experience' => $validatedData['experience'],
                'title'      => $validatedData['title'],
            ]);

            $token = $user->createToken('technician_token')->plainTextToken;

            Log::info('Técnico registrado exitosamente', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Técnico registrado exitosamente.',
                'data'    => [
                    'user'         => $user->load('technician'),
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en registro de técnico: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data'  => $request->except(['password']),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar el técnico.',
            ], 500);
        }
    }

    /**
     * Login del técnico.
     */
    public function login(LoginRequest $request)
    {
        $validatedData = $request->validated();

        $user = User::where('email', $validatedData['email'])
                    ->role('technician')
                    ->first();

        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            Log::warning('Intento de inicio de sesión fallido (Técnico)', ['email' => $validatedData['email']]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if ($user->status === 'blocked') {
            Log::warning('Intento de inicio de sesión de técnico bloqueado', ['user_id' => $user->id, 'email' => $user->email]);
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is blocked. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('technician_token')->plainTextToken;

        Log::info('Inicio de sesión exitoso (Técnico)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Inicio de sesión exitoso.',
            'data'    => [
                'user'         => $user->load('technician'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout del técnico (revocar token).
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        Log::info('Sesión cerrada (Técnico)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }
}

