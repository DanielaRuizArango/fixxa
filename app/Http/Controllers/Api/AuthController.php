<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Mail\ResetPasswordMail;


class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                Log::error('Error de validación en registro general', [
                    'errors' => $validator->errors()->toArray(),
                    'data' => $request->except(['password', 'password_confirmation']),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Usuario registrado exitosamente (General)', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en registro general: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar el usuario.',
            ], 500);
        }
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::error('Error de validación en login general', [
                'errors' => $validator->errors()->toArray(),
                'email' => $request->email,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Log::warning('Intento de inicio de sesión fallido (General)', ['email' => $request->email]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid login details',
            ], 401);
        }

        if ($user->status === 'blocked') {
            Log::warning('Intento de inicio de sesión de usuario bloqueado', ['user_id' => $user->id, 'email' => $user->email]);
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is blocked. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('Inicio de sesión exitoso (General)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status' => 'success',
            'message' => 'User logged in successfully',
            'data' => [
                'user' => $user,
                'role' => $user->role ?? ($user->getRoleNames()->first() ?? null),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        Log::info('Sesión cerrada (General)', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status' => 'success',
            'message' => 'User logged out successfully',
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;
        $user = User::where('email', '=', $email)->first();

        // Eliminar tokens anteriores para este correo
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Generar un nuevo token
        $token = Str::random(64);

        // Guardar el token en la base de datos
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);

        try {
            // Enviar el correo
            // Nota: En un entorno de producción, puedes incluir una URL completa.
            // Aquí enviamos solo el token por simplicidad o una URL de ejemplo.
            $resetUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/reset-password?token=' . $token . '&email=' . $email;
            Mail::to($email)->send(new ResetPasswordMail($user, $resetUrl));

            Log::info('Correo de recuperación enviado', ['email' => $email]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent to your email.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de recuperación: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reset link.',
            ], 500);
        }
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verificar el token
        $resetEntry = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetEntry) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token or email.',
            ], 400);
        }

        // El token expira después de cierto tiempo (por ejemplo, 60 minutos)
        $createdAt = \Carbon\Carbon::parse($resetEntry->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'status' => 'error',
                'message' => 'Token has expired.',
            ], 400);
        }

        // Actualizar la contraseña del usuario
        $user = User::where('email', '=', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Eliminar el token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        Log::info('Contraseña restablecida exitosamente', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully.',
        ]);
    }
}

