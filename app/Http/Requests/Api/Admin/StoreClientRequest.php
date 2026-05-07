<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'city' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'type_id' => 'required|string|max:20',
            'id_number' => 'required|string|max:20|unique:users',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
