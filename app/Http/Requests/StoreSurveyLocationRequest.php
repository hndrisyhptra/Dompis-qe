<?php

namespace App\Http\Requests;

use App\Support\CoordinatePair;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyLocationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('coordinates')) && $this->filled('coordinates')) {
            $pair = CoordinatePair::parse($this->input('coordinates'));
            if ($pair !== null) {
                $this->merge([...$pair, 'location_source' => 'manual', 'accuracy' => null]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'coordinates' => ['nullable', 'string', 'max:150', function ($attribute, $value, $fail) {
                if (! is_string($value) || CoordinatePair::parse($value) === null) {
                    $fail('Format koordinat tidak valid. Contoh: -7.257472, 112.752090. Gunakan titik untuk desimal.');
                }
            }],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'location_source' => ['nullable', Rule::in(['gps', 'manual'])],
        ];
    }
}
