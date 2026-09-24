<?php

namespace App\Http\Controllers;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\LopVisibilityService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['role', 'area', 'region', 'branch', 'serviceAreas']);

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

        $users = $query->latest()->paginate(20)->withQueryString();
        $users->getCollection()->each(function (User $user) {
            $user->setAttribute('scope_label', $user->hasRole(UserRole::ADMIN)
                ? $this->visibility->label($user)
                : ($user->branch?->name ?? 'Tanpa scope LOP'));
        });

        return view('users.index', [
            'users' => $users,
            'q' => $search,
            'roleFilter' => $roleId,
            ...$this->formOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', $this->formOptions());
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

        $user->load(['role', 'area', 'region', 'branch', 'serviceArea', 'serviceAreas.branch', 'historyEntries.actor']);

        return view('users.show', [
            'user' => $user,
            'scopeLabel' => $user->hasRole(UserRole::ADMIN) ? $this->visibility->label($user) : null,
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('serviceAreas');

        return view('users.edit', array_merge($this->formOptions(), ['user' => $user]));
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

    private function formOptions(): array
    {
        return [
            'roles' => Role::orderBy('name')->get(),
            'adminRoleId' => Role::query()->where('code', UserRole::ADMIN->value)->value('id'),
            'scopeTypes' => AdminScopeType::cases(),
            'areas' => Area::query()->where('is_active', true)->orderBy('name')->get(),
            'regions' => Region::query()->with('area')->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::query()->with('regionRef.area')->where('is_active', true)->orderBy('name')->get(),
            'serviceAreas' => ServiceArea::query()->with('branch.regionRef')->where('is_active', true)->orderBy('workzone')->get(),
        ];
    }
}
