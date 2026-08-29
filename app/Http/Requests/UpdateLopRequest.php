<?php

namespace App\Http\Requests;

use App\Enums\WbsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        $lopId = $this->route('qe_lop')?->id_qe_lops;

        return [
            'kode_lop' => [
                'required', 'string', 'max:100',
                Rule::unique('qe_lops', 'kode_lop')->ignore($lopId, 'id_qe_lops'),
            ],
            'nama_lop' => ['required', 'string', 'max:255'],
            'wbs_type' => ['required', Rule::enum(WbsType::class)],
            'sto' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'package_id' => ['nullable', 'integer'],
        ];
    }
}
