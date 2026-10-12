<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function context(Request $request): JsonResponse
    {
        // Endpoint same-origin untuk form autentikasi; tidak membuka CORS/JSONP.
        abort_if($request->header('Sec-Fetch-Site') === 'cross-site', 403);

        return response()->json([
            'csrf_token' => $request->session()->token(),
            'user_id' => $request->user()?->getKey(),
            'redirect_url' => route($request->user()?->postLoginRouteName() ?? 'login', [], false),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Vary' => 'Cookie',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Session security: regenerate ID setelah login supaya session
        // sebelum-login (anonymous) tidak bisa dipakai lagi - mencegah
        // session fixation. Pola sama seperti dompis-cons.
        $request->session()->regenerate();

        $request->user()->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();

        // Workspace teknisi bersifat mobile-first dan tidak boleh ditimpa
        // intended URL lama (mis. /lop dari session sebelum login).
        if ($request->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.dashboard');
        }

        return redirect()->intended(route($request->user()->postLoginRouteName(), absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
