<?php

namespace App\Http\Requests\Api\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'technician';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users,email,' . $userId,
            'phone'      => 'required|string|max:20',
            'address'    => 'required|string|max:255',
            'city'       => 'required|string|max:50',
            'experience' => 'required|string|max:2000',
            'title'      => 'required|string|max:255',
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
            'experience.required' => 'La experiencia es obligatoria.',
            'title.required'      => 'El título es obligatorio.',
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
