<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Registro de un nuevo técnico.
     * Campos: nombre, cédula, correo, dirección, experiencia, título, imagen.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'cedula'      => 'required|string|max:20|unique:technicians,cedula',
            'email'       => 'required|string|email|max:255|unique:users',
            'address'     => 'required|string|max:255',
            'experience'  => 'required|string|max:1000',
            'title'       => 'required|string|max:255',
            'password'    => 'required|string|min:8|confirmed',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'name.required'       => 'El nombre es obligatorio.',
            'cedula.required'     => 'La cédula es obligatoria.',
            'cedula.unique'       => 'Esta cédula ya está registrada.',
            'email.required'      => 'El correo es obligatorio.',
            'email.unique'        => 'Este correo ya está registrado.',
            'address.required'    => 'La dirección es obligatoria.',
            'experience.required' => 'La experiencia es obligatoria.',
            'title.required'      => 'El título es obligatorio.',
            'password.required'   => 'La contraseña es obligatoria.',
            'password.min'        => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'  => 'Las contraseñas no coinciden.',
            'image.image'         => 'El archivo debe ser una imagen.',
            'image.mimes'         => 'La imagen debe ser jpeg, png, jpg o webp.',
            'image.max'           => 'La imagen no debe superar los 2 MB.',
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
            $imagePath = $request->file('image')->store('technicians/images', 'public');
        }

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'technician',
        ]);

        // Crear perfil de técnico
        $technician = \App\Models\Technician::create([
            'user_id'    => $user->id,
            'cedula'     => $request->cedula,
            'address'    => $request->address,
            'experience' => $request->experience,
            'title'      => $request->title,
            'image'      => $imagePath,
        ]);

        $token = $user->createToken('technician_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Técnico registrado exitosamente.',
            'data'    => [
                'user'         => $user->load('technician'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login del técnico.
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
                    ->where('role', 'technician')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        $token = $user->createToken('technician_token')->plainTextToken;

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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sesión cerrada exitosamente.',
        ]);
    }
}
