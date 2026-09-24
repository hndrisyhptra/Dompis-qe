@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">User Management</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Kelola akun, role, dan scope akses lokasi.</p>
        </div>
        <button type="button" onclick="document.getElementById('create-user-modal').showModal()"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Tambah User
        </button>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row gap-3 mb-4">
        <input
            type="text"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama, username, atau NIK..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
        >
        <select name="role" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <option value="">Semua Role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected($roleFilter == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Filter
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Username / NIK</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Scope Akses</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($users as $user)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">
                        {{ $user->username }}
                        @if ($user->nik)
                            <span class="text-ink-400 dark:text-ink-500">· {{ $user->nik }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-badge variant="info">{{ $user->role?->name ?? '—' }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">
                        <span class="block max-w-xs truncate" title="{{ $user->scope_label }}">{{ $user->scope_label }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$user->isActive() ? 'success' : 'neutral'">
                            {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-table-action label="Detail User" :href="route('users.show', $user)">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                            </x-table-action>
                            <x-table-action label="Edit User" tone="info" :onclick="'document.getElementById(\'edit-user-modal-'.$user->id_user.'\').showModal()'">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 3.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 15.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 3.487Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125V18A2.625 2.625 0 0 1 16.875 20.625H6A2.625 2.625 0 0 1 3.375 18V7.125A2.625 2.625 0 0 1 6 4.5h8.25" /></svg>
                            </x-table-action>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada user.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    @foreach ($users as $user)
        @include('users._edit-modal', ['user' => $user])
    @endforeach

    <x-modal id="create-user-modal" title="Tambah User Baru" size="xl">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5"
              x-data="{ roleId: @js((string) old('role_id', '')), adminRoleId: @js((string) $adminRoleId), scopeType: @js(old('admin_scope_type', '')), showPassword: false }">
            @csrf

            <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3 dark:border-blue-900/50 dark:bg-blue-950/20">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-6.75-3.75A3.75 3.75 0 1 1 6.75 6.75a3.75 3.75 0 0 1 7.5 0ZM3 20.25a6.75 6.75 0 0 1 13.5 0v.75H3v-.75Z" /></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-ink-900 dark:text-white">Buat akun dan tentukan aksesnya</p>
                    <p class="mt-0.5 text-xs leading-5 text-ink-500 dark:text-ink-400">Role Admin wajib memiliki scope lokasi. Role lain dapat dihubungkan ke Branch sesuai kebutuhan operasional.</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                    <p class="font-semibold">Data user belum dapat disimpan</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                        @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
                <div class="mb-4 flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">01</span>
                    <div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Identitas pengguna</h4><p class="text-xs text-ink-400">Informasi utama pemilik akun.</p></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><x-input name="name" id="create_name" label="Nama Lengkap" placeholder="Nama sesuai identitas" required autocomplete="name" /></div>
                    <x-input name="username" id="create_username" label="Username" placeholder="Digunakan untuk login" required autocomplete="off" />
                    <x-input name="nik" id="create_nik" label="NIK (opsional)" placeholder="Jika berbeda dari username" autocomplete="off" />
                    <x-input name="email" id="create_email" label="Email (opsional)" type="email" placeholder="nama@perusahaan.com" autocomplete="email" />
                    <x-input name="phone" id="create_phone" label="No. Telepon (opsional)" placeholder="08xxxxxxxxxx" autocomplete="tel" />
                </div>
            </section>

            <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
                <div class="mb-4 flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">02</span>
                    <div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Role dan akses</h4><p class="text-xs text-ink-400">Atur kewenangan serta cakupan wilayah.</p></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="role_id" id="create_role_id" label="Role" placeholder="Pilih role" x-model="roleId" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </x-select>
                    <x-select name="status" id="create_status" label="Status akun">
                        <option value="active" @selected(old('status', 'active') === 'active')>Aktif — dapat login</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Nonaktif — akses ditangguhkan</option>
                    </x-select>
                    <div class="sm:col-span-2">
                        @include('users._access-scope', ['user' => null, 'fieldPrefix' => 'create_'])
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">03</span>
                        <div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Keamanan akun</h4><p class="text-xs text-ink-400">Gunakan minimal 8 karakter.</p></div>
                    </div>
                    <button type="button" @click="showPassword = !showPassword" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400" x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'"></button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="password" id="create_password" label="Password" type="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="new-password" />
                    <x-input name="password_confirmation" id="create_password_confirmation" label="Konfirmasi Password" type="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="new-password" />
                </div>
            </section>

            <div class="sticky bottom-0 -mx-5 -mb-5 flex items-center justify-end gap-2 border-t border-ink-100 bg-white/95 px-5 py-3 backdrop-blur dark:border-ink-700 dark:bg-ink-900/95">
                <button type="button" onclick="document.getElementById('create-user-modal').close()" class="min-h-10 rounded-lg px-4 text-sm font-semibold text-ink-600 transition hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">Batal</button>
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Simpan User
                </button>
            </div>
        </form>
    </x-modal>

    @if ($errors->any() && ! old('_edit_user_id'))
        <script>
            document.addEventListener('DOMContentLoaded', () => document.getElementById('create-user-modal')?.showModal());
        </script>
    @elseif ($errors->any() && old('_edit_user_id'))
        <script>
            document.addEventListener('DOMContentLoaded', () => document.getElementById(@js('edit-user-modal-'.old('_edit_user_id')))?.showModal());
        </script>
    @endif
</div>
@endsection
