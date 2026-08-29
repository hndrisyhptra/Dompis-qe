<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id_user;

        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id_branch'],
            'nik' => [
                'nullable', 'string', 'max:100',
                Rule::unique('users', 'nik')->ignore($userId, 'id_user'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($userId, 'id_user'),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId, 'id_user'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
