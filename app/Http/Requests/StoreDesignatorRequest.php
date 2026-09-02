<?php

namespace App\Http\Requests;

<<<<<<< HEAD
use Illuminate\Foundation\Http\FormRequest;
=======
use App\Enums\DesignatorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c

class StoreDesignatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:designators,code'],
            'item_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
<<<<<<< HEAD
            'designator_type_id' => ['required', 'integer', 'exists:designator_types,id_designator_type'],
            'designator_category_id' => ['nullable', 'integer', 'exists:designator_categories,id_designator_category'],
=======
            'type' => ['required', Rule::enum(DesignatorType::class)],
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        ];
    }
}
