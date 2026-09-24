<?php

namespace App\Http\Requests;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id_user;

        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id_branch'],
            'admin_scope_type' => ['nullable', Rule::enum(AdminScopeType::class)],
            'area_id' => ['nullable', 'integer', 'exists:areas,id_area'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id_region'],
            'service_area_id' => ['nullable', 'integer', 'exists:service_areas,id_service_area'],
            'service_area_ids' => ['nullable', 'array', 'max:100'],
            'service_area_ids.*' => ['integer', 'distinct', 'exists:service_areas,id_service_area'],
            'nik' => [
                'nullable', 'string', 'max:100',
                Rule::unique('users', 'nik')->ignore($userId, 'id_user'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($userId, 'id_user'),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId, 'id_user'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($errors): void {
            $roleCode = Role::find($this->integer('role_id'))?->code;
            if ($roleCode !== UserRole::ADMIN->value) {
                return;
            }

            $scope = AdminScopeType::tryFrom((string) $this->input('admin_scope_type'));
            if ($scope === null) {
                $errors->errors()->add('admin_scope_type', 'Pilih level scope untuk role Admin.');

                return;
            }

            $required = match ($scope) {
                AdminScopeType::AREA => ['area_id', 'Pilih Area untuk Admin Area.'],
                AdminScopeType::REGION => ['region_id', 'Pilih Region untuk Admin Region.'],
                AdminScopeType::BRANCH => ['branch_id', 'Pilih Branch untuk Admin Branch.'],
                AdminScopeType::SERVICE_AREA => ['service_area_ids', 'Pilih minimal satu Service Area.'],
            };
            if (blank($this->input($required[0]))) {
                $errors->errors()->add($required[0], $required[1]);
            }
        });
    }
}
