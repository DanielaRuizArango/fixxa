<?php

namespace App\Http\Requests\Api\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'phone'      => 'required|string|max:20',
            'address'    => 'required|string|max:255',
            'city'       => 'required|string|max:50',
            'type_id'    => 'required|string|max:20',
            'id_number'  => 'required|string|max:20|unique:users',
            'experience' => 'required|string|max:2000',
            'title'      => 'required|string|max:255',
            'password'   => 'required|string|min:8',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required'       => 'El nombre es obligatorio.',
            'email.required'      => 'El correo es obligatorio.',
            'email.unique'        => 'Este correo ya está registrado.',
            'phone.required'      => 'El celular es obligatorio.',
            'address.required'    => 'La dirección es obligatoria.',
            'city.required'       => 'La ciudad es obligatoria.',
            'city.max'            => 'La ciudad no debe superar los 50 caracteres.',
            'type_id.required'    => 'El tipo de identificación es obligatorio.',
            'id_number.required'  => 'El número de identificación es obligatorio.',
            'id_number.unique'    => 'Este número de identificación ya está registrado.',
            'experience.required' => 'La experiencia es obligatoria.',
            'title.required'      => 'El título es obligatorio.',
            'password.required'   => 'La contraseña es obligatoria.',
            'password.min'        => 'La contraseña debe tener al menos 8 caracteres.',
            // Removed password.confirmed message
            'image.image'         => 'El archivo debe ser una imagen.',
            'image.mimes'         => 'La imagen debe ser jpeg, png, jpg o webp.',
            'image.max'           => 'La imagen no debe superar los 2 MB.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
