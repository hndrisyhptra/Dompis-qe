<?php

namespace App\Http\Requests;

use App\Enums\WbsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\QeLop::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'kode_lop' => ['required', 'string', 'max:100', 'unique:qe_lops,kode_lop'],
            'nama_lop' => ['required', 'string', 'max:255'],
            'wbs_type' => ['required', Rule::enum(WbsType::class)],
            'sto' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'package_id' => ['nullable', 'integer'],
        ];
    }
}
