<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAissImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import-aiss') ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Gunakan file Excel XLSX atau XLS hasil ekspor AISS.', 'file.max' => 'Ukuran file maksimal 10 MB.'];
    }
}
