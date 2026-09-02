@extends('layouts.app')

@section('title', 'Tambah User')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah User</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Buat akun pengguna baru.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
            @csrf

            <x-input name="name" label="Nama Lengkap" placeholder="Nama sesuai identitas" />
            <x-input name="username" label="Username (NIK)" placeholder="Digunakan untuk login" />
            <x-input name="nik" label="NIK (opsional, jika berbeda dari username)" />
            <x-input name="email" label="Email (opsional)" type="email" />
            <x-input name="phone" label="No. Telepon (opsional)" />

            <x-select name="role_id" label="Role" placeholder="Pilih role">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </x-select>

            <x-select name="branch_id" label="Branch (opsional)" placeholder="Pilih branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id_branch }}" @selected(old('branch_id') == $branch->id_branch)>{{ $branch->name }}</option>
                @endforeach
            </x-select>

            <x-select name="status" label="Status">
                <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status') === 'inactive')>Nonaktif</option>
            </x-select>

            <x-input name="password" label="Password" type="password" />
            <x-input name="password_confirmation" label="Konfirmasi Password" type="password" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('users.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
