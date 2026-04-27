<?php

namespace App\Http\Requests\Api\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

use Illuminate\Support\Facades\Log;

class StoreCaseResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('technician');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_case_id' => 'required|exists:service_cases,id',
            'estimated_cost'  => 'required|numeric|min:0',
            'questions'       => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'service_case_id.required' => 'El caso de servicio es obligatorio.',
            'service_case_id.exists'   => 'El caso de servicio no existe.',
            'estimated_cost.required'  => 'El costo estimado es obligatorio.',
            'estimated_cost.numeric'   => 'El costo estimado debe ser un número.',
            'estimated_cost.min'       => 'El costo estimado no puede ser negativo.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('Error de validación al enviar respuesta de caso', [
            'errors'  => $validator->errors()->toArray(),
            'user_id' => $this->user()->id,
            'data'    => $this->all(),
        ]);

        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
