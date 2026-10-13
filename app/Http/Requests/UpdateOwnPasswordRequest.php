<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'current_password:web'],
            'password' => ['bail', 'required', 'string', 'min:8', 'different:current_password', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail('Password baru maksimal 72 byte dan tidak boleh berisi karakter null.');
                }
            }],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Masukkan password saat ini.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Masukkan password baru.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password_confirmation.required' => 'Ulangi password baru untuk konfirmasi.',
        ];
    }
}
