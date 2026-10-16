<?php

namespace App\Http\Requests;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\QeLop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DataLopFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(...UserRole::adminLevel())
            && $this->user()->can('viewAny', QeLop::class);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', Rule::enum(ProgramType::class)],
            'status' => ['nullable', Rule::enum(LopStatus::class)],
            'branch' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['q' => 'pencarian', 'program' => 'program', 'status' => 'status Dompis', 'branch' => 'branch'];
    }
}
