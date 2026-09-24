@php
    $modalId = 'edit-user-modal-'.$user->id_user;
    $fieldPrefix = 'edit_'.$user->id_user.'_';
    $reopening = (string) old('_edit_user_id') === (string) $user->id_user;
    $fieldValue = fn (string $key, mixed $default = null) => $reopening ? old($key, $default) : $default;
@endphp

<x-modal :id="$modalId" :title="'Edit User · '.$user->name" size="xl">
    <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5" x-data="{ roleId: @js((string) $fieldValue('role_id', $user->role_id)), adminRoleId: @js((string) $adminRoleId), scopeType: @js($fieldValue('admin_scope_type', $user->admin_scope_type?->value ?? '')), showPassword: false }">
        @csrf
        @method('PUT')
        <input type="hidden" name="_edit_user_id" value="{{ $user->id_user }}">

        <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3 dark:border-blue-900/50 dark:bg-blue-950/20">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 3.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 15.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 3.487Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125V18A2.625 2.625 0 0 1 16.875 20.625H6A2.625 2.625 0 0 1 3.375 18V7.125A2.625 2.625 0 0 1 6 4.5h8.25"/></svg></span>
            <div><p class="text-sm font-semibold text-ink-900 dark:text-white">Perbarui profil dan akses pengguna</p><p class="mt-0.5 text-xs leading-5 text-ink-500 dark:text-ink-400">Perubahan role atau scope langsung menentukan cakupan data yang dapat dikelola akun ini.</p></div>
        </div>

        @if ($reopening && $errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"><p class="font-semibold">Perubahan belum dapat disimpan</p><ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif

        <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
            <div class="mb-4 flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">01</span><div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Identitas pengguna</h4><p class="text-xs text-ink-400">Informasi utama pemilik akun.</p></div></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-input name="name" :id="$fieldPrefix.'name'" label="Nama Lengkap" :value="$user->name" :use-old="$reopening" :show-errors="$reopening" required autocomplete="name" /></div>
                <x-input name="username" :id="$fieldPrefix.'username'" label="Username" :value="$user->username" :use-old="$reopening" :show-errors="$reopening" required autocomplete="off" />
                <x-input name="nik" :id="$fieldPrefix.'nik'" label="NIK (opsional)" :value="$user->nik" :use-old="$reopening" :show-errors="$reopening" autocomplete="off" />
                <x-input name="email" :id="$fieldPrefix.'email'" label="Email (opsional)" type="email" :value="$user->email" :use-old="$reopening" :show-errors="$reopening" autocomplete="email" />
                <x-input name="phone" :id="$fieldPrefix.'phone'" label="No. Telepon (opsional)" :value="$user->phone" :use-old="$reopening" :show-errors="$reopening" autocomplete="tel" />
            </div>
        </section>

        <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
            <div class="mb-4 flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">02</span><div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Role dan akses</h4><p class="text-xs text-ink-400">Atur kewenangan serta cakupan wilayah.</p></div></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="role_id" :id="$fieldPrefix.'role_id'" :show-errors="$reopening" label="Role" placeholder="Pilih role" x-model="roleId" required>
                    @foreach ($roles as $role)<option value="{{ $role->id }}" @selected($fieldValue('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>@endforeach
                </x-select>
                <x-select name="status" :id="$fieldPrefix.'status'" :show-errors="$reopening" label="Status akun">
                    <option value="active" @selected($fieldValue('status', $user->status) === 'active')>Aktif — dapat login</option>
                    <option value="inactive" @selected($fieldValue('status', $user->status) === 'inactive')>Nonaktif — akses ditangguhkan</option>
                </x-select>
                <div class="sm:col-span-2">@include('users._access-scope', ['user' => $user, 'fieldPrefix' => $fieldPrefix, 'useOldInput' => $reopening])</div>
            </div>
        </section>

        <section class="rounded-xl border border-ink-200 p-4 dark:border-ink-700">
            <div class="mb-4 flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">03</span><div><h4 class="text-sm font-semibold text-ink-900 dark:text-white">Keamanan akun</h4><p class="text-xs text-ink-400">Kosongkan bila password tidak diubah.</p></div></div><button type="button" @click="showPassword = !showPassword" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400" x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'"></button></div>
            <div class="grid gap-4 sm:grid-cols-2"><x-input name="password" :id="$fieldPrefix.'password'" label="Password Baru" type="password" x-bind:type="showPassword ? 'text' : 'password'" :show-errors="$reopening" autocomplete="new-password" /><x-input name="password_confirmation" :id="$fieldPrefix.'password_confirmation'" label="Konfirmasi Password Baru" type="password" x-bind:type="showPassword ? 'text' : 'password'" :show-errors="$reopening" autocomplete="new-password" /></div>
        </section>

        <div class="sticky bottom-0 -mx-5 -mb-5 flex items-center justify-end gap-2 border-t border-ink-100 bg-white/95 px-5 py-3 backdrop-blur dark:border-ink-700 dark:bg-ink-900/95"><button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="min-h-10 rounded-lg px-4 text-sm font-semibold text-ink-600 transition hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">Batal</button><button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>Simpan Perubahan</button></div>
    </form>
</x-modal>
