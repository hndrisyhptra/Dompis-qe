<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionExpiredResponse
{
    public static function make(Request $request): Response
    {
        $user = $request->user();
        $destination = route($user?->postLoginRouteName() ?? 'login', [], false);
        $message = $user
            ? 'Sesi berubah atau token halaman sudah kedaluwarsa. Tindakan belum diproses. Buka halaman terbaru lalu coba kembali.'
            : 'Sesi Anda sudah kedaluwarsa. Silakan masuk kembali, lalu ulangi tindakan Anda.';

        if ($request->expectsJson()) {
            $response = response()->json([
                'message' => $message,
                'code' => 'session_expired',
                'login_url' => route('login', [], false),
                'recovery_url' => $destination,
            ], 419);
        } elseif ($request->is('login', 'logout')) {
            $response = redirect($destination)->with('auth_warning', $message);
            if ($request->is('login') && ! $user) {
                // Password, token, file, dan payload pekerjaan tidak pernah disimpan ulang.
                $response->withInput($request->only('username'));
            }
        } else {
            $response = response()->view('errors.419', [
                'message' => $message,
                'recoveryUrl' => $destination,
                'recoveryLabel' => $user ? 'Buka halaman terbaru' : 'Masuk kembali',
            ], 419);
        }

        $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate');
        $response->headers->set('Vary', 'Cookie');

        return $response;
    }
}
