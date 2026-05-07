<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $user = $this->user();
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
