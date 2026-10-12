<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserImportRequest extends FormRequest
{
    protected $errorBag = 'userImport';

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'extensions:xlsx,xls,csv', 'max:5120']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file user terlebih dahulu.',
            'file.mimes' => 'Gunakan file Excel XLSX/XLS atau CSV sesuai template user.',
            'file.extensions' => 'Ekstensi file harus XLSX, XLS, atau CSV.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
