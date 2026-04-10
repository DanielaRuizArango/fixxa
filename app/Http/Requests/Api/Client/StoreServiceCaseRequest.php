<?php

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

use Illuminate\Support\Facades\Log;

class StoreServiceCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'client';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'city'        => 'nullable|string|max:100',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required'       => 'El título es obligatorio.',
            'description.required' => 'La descripción es obligatoria.',
            'images.array'         => 'Las imágenes deben enviarse como una lista.',
            'images.*.image'       => 'El archivo debe ser una imagen.',
            'images.*.mimes'       => 'La imagen debe ser jpeg, png, jpg o webp.',
            'images.*.max'         => 'La imagen no debe superar los 2 MB.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('Error de validación al crear caso de servicio', [
            'errors'  => $validator->errors()->toArray(),
            'user_id' => $this->user()->id,
        ]);

        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
