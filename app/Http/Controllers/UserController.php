<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['role', 'branch']);

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($roleId = $request->integer('role')) {
            $query->where('role_id', $roleId);
        }

        return view('users.index', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
            'roles' => Role::orderBy('name')->get(),
            'q' => $search,
            'roleFilter' => $roleId,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->create($request->validated(), $request->user());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil dibuat.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['role', 'branch', 'historyEntries.actor']);

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated(), $request->user());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil dihapus.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);

        $this->userService->activate($user, $request->user());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil diaktifkan.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $this->userService->deactivate($user, $request->user());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil dinonaktifkan.');
    }

    public function restore(Request $request, string $id_user): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id_user);

        $this->authorize('restore', $user);

        $this->userService->restore($user, $request->user());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil dipulihkan.');
    }
}
