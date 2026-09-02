<?php

namespace App\Http\Requests;

use App\Services\LopNamingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLopNameFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'template' => ['required', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! str_contains((string) $this->input('template'), '{incident}')) {
                $validator->errors()->add('template', 'Format wajib memuat token {incident}.');
            }

            preg_match_all('/\{[^}]+\}/', (string) $this->input('template'), $matches);
            $unknown = array_diff(array_unique($matches[0]), array_keys(
                app(LopNamingService::class)->availableTokens()
            ));

            if ($unknown !== []) {
                $validator->errors()->add(
                    'template',
                    'Token tidak dikenali: '.implode(', ', $unknown)
                );
            }
        }];
    }
}
