@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit User</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $user->name }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="name" label="Nama Lengkap" :value="$user->name" />
            <x-input name="username" label="Username (NIK)" :value="$user->username" />
            <x-input name="nik" label="NIK (opsional, jika berbeda dari username)" :value="$user->nik" />
            <x-input name="email" label="Email (opsional)" type="email" :value="$user->email" />
            <x-input name="phone" label="No. Telepon (opsional)" :value="$user->phone" />

            <x-select name="role_id" label="Role" placeholder="Pilih role">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>
                @endforeach
            </x-select>

            <x-select name="branch_id" label="Branch (opsional)" placeholder="Pilih branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id_branch }}" @selected(old('branch_id', $user->branch_id) == $branch->id_branch)>{{ $branch->name }}</option>
                @endforeach
            </x-select>

            <x-select name="status" label="Status">
                <option value="active" @selected(old('status', $user->status) === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Nonaktif</option>
            </x-select>

            <x-input name="password" label="Password Baru (kosongkan jika tidak diubah)" type="password" />
            <x-input name="password_confirmation" label="Konfirmasi Password Baru" type="password" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('users.show', $user) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
