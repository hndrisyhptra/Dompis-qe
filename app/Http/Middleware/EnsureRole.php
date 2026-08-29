<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role generik untuk route-level gating kasar (mis. seluruh
 * grup route admin). Untuk otorisasi granular per-aksi/per-record (mis.
 * modul LOP), tetap gunakan Policy - middleware ini BUKAN pengganti Policy.
 *
 * Pemakaian: Route::middleware('role:ADMIN,SUPER_ADMIN')->group(...)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(
            fn (string $code) => UserRole::from($code),
            $roles
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
