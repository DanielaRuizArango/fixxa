<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'type_id' => 'nullable|string|max:20',
            'id_number' => 'nullable|string|max:20|unique:users',
            'image' => 'nullable|image|max:2048',
            'spatie_role' => 'nullable|string'
        ];
    }
}
