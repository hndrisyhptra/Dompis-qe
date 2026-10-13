@extends($isTechnician ? 'layouts.technician' : 'layouts.app')
@section('title', 'Ganti Password')
@section('header', 'Ganti Password')
@section('content')
<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ $isTechnician ? route('technician.profile') : route($account->postLoginRouteName()) }}" class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-ink-600 dark:text-ink-300">← Kembali</a>
    <section class="rounded-md border border-ink-200 bg-white p-4 shadow-sm dark:border-ink-700 dark:bg-ink-900 sm:p-6">
        <h1 class="text-xl font-extrabold text-ink-900 dark:text-white">Ganti Password</h1>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Amankan akun Anda dengan password yang hanya Anda ketahui.</p>
        <div class="mt-4 rounded-md border border-ink-200 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800"><p class="text-sm font-bold">{{ $account->name }}</p><p class="mt-1 break-words text-xs text-ink-500 dark:text-ink-300">{{ $accountScopeLabel }}</p></div>
        <form method="POST" action="{{ route('account.password.update') }}" class="mt-5 space-y-4" x-data="{ saving: false }" @submit="saving = true" :aria-busy="saving">
            @csrf
            @method('PUT')
            @foreach ([['current_password', 'Password saat ini', 'current-password'], ['password', 'Password baru', 'new-password'], ['password_confirmation', 'Konfirmasi password baru', 'new-password']] as [$field, $label, $autocomplete])
                <div x-data="{ visible: false }">
                    <label for="{{ $field }}" class="mb-1.5 block text-xs font-bold text-ink-700 dark:text-ink-200">{{ $label }}</label>
                    <div class="relative">
                        <input id="{{ $field }}" name="{{ $field }}" type="password" :type="visible ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" required @if($field !== 'current_password') minlength="8" @endif class="min-h-11 w-full rounded-md border border-ink-300 bg-white pl-3 pr-24 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-600 dark:bg-ink-800" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        <button type="button" @click="visible = !visible" :aria-pressed="visible" aria-label="Tampilkan atau sembunyikan {{ strtolower($label) }}" class="absolute inset-y-0 right-0 px-3 text-xs font-bold text-brand-600 dark:text-brand-400" x-text="visible ? 'Sembunyikan' : 'Lihat'">Lihat</button>
                    </div>
                    @error($field)<p id="{{ $field }}-error" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <p class="rounded-md bg-ink-50 p-3 text-xs leading-5 text-ink-500 dark:bg-ink-800 dark:text-ink-300">Minimal 8 karakter, berbeda dari password lama. Setelah disimpan, sesi lain perlu login ulang. Perangkat ini tetap login.</p>
            <button type="submit" :disabled="saving" class="min-h-11 w-full rounded-md bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"><span x-show="!saving">Simpan Password</span><span x-show="saving" x-cloak>Menyimpan…</span></button>
        </form>
    </section>
</div>
@endsection
