<?php

namespace App\Http\Requests;

use App\Models\QeLop;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoqImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QeLop::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }
}
