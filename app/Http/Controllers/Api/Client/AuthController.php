<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Registro de un nuevo cliente.
     * Campos: nombre, correo, celular, dirección, cédula, imagen.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'phone'     => 'required|string|max:20',
            'address'   => 'required|string|max:255',
            'cedula'    => 'required|string|max:20|unique:clients,cedula',
            'password'  => 'required|string|min:8|confirmed',
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'name.required'    => 'El nombre es obligatorio.',
            'email.required'   => 'El correo es obligatorio.',
            'email.unique'     => 'Este correo ya está registrado.',
            'phone.required'   => 'El celular es obligatorio.',
            'address.required' => 'La dirección es obligatoria.',
            'cedula.required'  => 'La cédula es obligatoria.',
            'cedula.unique'    => 'Esta cédula ya está registrada.',
            'password.required'=> 'La contraseña es obligatoria.',
            'password.min'     => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'=> 'Las contraseñas no coinciden.',
            'image.image'      => 'El archivo debe ser una imagen.',
            'image.mimes'      => 'La imagen debe ser jpeg, png, jpg o webp.',
            'image.max'        => 'La imagen no debe superar los 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Guardar imagen si fue enviada
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('clients/images', 'public');
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'client',
        ]);

        // Crear perfil de cliente
        $client = \App\Models\Client::create([
            'user_id' => $user->id,
            'phone'   => $request->phone,
            'address' => $request->address,
            'cedula'  => $request->cedula,
            'image'   => $imagePath,
        ]);

        $token = $user->createToken('client_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Cliente registrado exitosamente.',
            'data'    => [
                'user'         => $user->load('client'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login del cliente.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'Ingrese un correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('role', 'client')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        $token = $user->createToken('client_token')->plainTextToken;

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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }
}
