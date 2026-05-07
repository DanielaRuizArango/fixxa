<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $userId = $this->route('admin'); // Assuming 'admin' is the parameter name in route
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $userId,
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:255',
            'type_id' => 'sometimes|string|max:20',
            'id_number' => 'sometimes|string|max:20|unique:users,id_number,' . $userId,
            'image' => 'nullable|image|max:2048',
            'status' => 'sometimes|in:active,blocked',
        ];
    }
}
