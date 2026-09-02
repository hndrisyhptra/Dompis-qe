<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller. AuthorizesRequests menyediakan $this->authorize() yang
 * dipakai LopController & UserController - tanpa trait ini authorize()
 * gagal dengan "undefined method" (bug laten, baru ketahuan saat modul
 * User Management ditest lewat HTTP - LopPermissionTest sebelumnya cuma
 * mengetes lewat FormRequest::authorize(), tidak pernah memanggil
 * $this->authorize() controller lewat request GET beneran).
 */
abstract class Controller
{
    use AuthorizesRequests;
}
