<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('qe_lop')) ?? false;
    }

    /**
     * NOTE: sebelumnya rule ini mengecek 'users.id'/'users.role' - kolom
     * lama sebelum modul Authentication merename users.id -> id_user dan
     * mengganti kolom role (string) dengan role_id (FK ke roles). Bug laten
     * ini tidak pernah ketahuan karena tidak ada test yang benar-benar
     * submit assign lewat HTTP (LopWorkflowTest memanggil LopService
     * langsung, LopPermissionTest hanya menguji jalur yang diblokir Policy
     * sebelum validasi ini sempat jalan).
     */
    public function rules(): array
    {
        $teknisiRoleId = Role::where('code', UserRole::TEKNISI->value)->value('id');

        return [
            'technician_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id_user')
                    ->where('role_id', $teknisiRoleId)
                    ->where('status', 'active'),
            ],
            'return_to' => ['nullable', Rule::in(['index', 'show'])],
        ];
    }
}
