<?php

namespace App\Http\Requests;

use App\Models\QeLop;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QeLop::class) ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Gunakan file XLSX, XLS, atau CSV sesuai template Bulk LOP.'];
    }
}
