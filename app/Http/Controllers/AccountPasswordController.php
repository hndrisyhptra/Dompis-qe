<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateOwnPasswordRequest;
use App\Services\AccountScopeService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class AccountPasswordController extends Controller
{
    public function edit(Request $request, AccountScopeService $scope): View
    {
        abort_unless($request->user()->isActive(), 403);

        return view('account.password', [
            'account' => $request->user(),
            'accountScopeLabel' => $scope->label($request->user()),
            'isTechnician' => $request->user()->hasRole(UserRole::TEKNISI),
        ]);
    }

    public function update(UpdateOwnPasswordRequest $request, UserService $users): RedirectResponse
    {
        $user = $users->changeOwnPassword(
            $request->user(), $request->validated('current_password'), $request->validated('password')
        );
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate(true);
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));

        return redirect()->route('account.password.edit')->with('status', 'Password berhasil diperbarui. Sesi lain perlu login kembali; perangkat ini tetap login.');
    }
}
