@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="min-h-screen flex flex-col lg:flex-row">

    {{-- LEFT SECTION — brand & context (hidden di mobile, full di desktop) --}}
    <div
        class="relative hidden overflow-hidden bg-ink-950 bg-cover bg-center bg-no-repeat p-12 lg:flex lg:w-1/2 lg:flex-col lg:justify-between xl:p-16"
        style="background-image: url('{{ asset('images/bg-dompis-qe.webp') }}')">

        <div class="relative z-10 max-w-md">
            <div class="-mt-4 mb-10 flex items-center gap-4 xl:-mt-6">
                <img src="{{ asset('images/logo-dompis-qe.webp') }}" alt="{{ config('app.name') }}" class="h-14 w-auto shrink-0 object-contain">
                <span class="text-3xl font-extrabold tracking-tight text-white">{{ config('app.name') }}</span>
            </div>
            {{-- <h1 class="text-3xl xl:text-4xl font-bold text-white leading-tight tracking-tight">
                Platform Operation QE
            </h1> --}}
            <p class="mt-4 text-base leading-relaxed text-white">
                Kelola LOP, assignment teknisi, survey lapangan, evidence, dan approval
                dalam satu sistem — dari input hingga penyelesaian pekerjaan.
            </p>

        </div>

        <p class="relative z-10 text-xs text-ink-300">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Internal application — akses terbatas untuk personel resmi.
        </p>
    </div>

    {{-- RIGHT SECTION — login card --}}
    <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10">

        <div class="w-full max-w-sm">

            <x-card>
                 {{-- Logo --}}
                <div class="mb-5 flex flex-col items-center gap-2">
                    <img src="{{ asset('images/logo-dompis-qe.webp') }}" alt="{{ config('app.name') }}" class="h-20 w-auto object-contain">
                </div>
                <h2 class="text-center text-xl font-bold text-ink-900 dark:text-ink-50">
                    Selamat Datang!
                </h2>

                <p class="mt-1.5 text-center text-sm text-ink-500 dark:text-ink-400">
                    Gunakan NIK dan kata sandi yang terdaftar.
                </p>

                @if ($errors->any())
                    <div class="mt-5 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 px-4 py-3">
                        <p class="text-sm text-brand-700 dark:text-brand-300 font-medium">
                            {{ $errors->first() }}
                        </p>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                        <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">
                            {{ session('status') }}
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <x-input
                        name="username"
                        label="NIK"
                        placeholder="Masukkan NIK Anda"
                        autofocus
                        autocomplete="username"
                    />

                    <div x-data="{ showPassword: false }">
                        <label for="password" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">
                            Kata Sandi
                        </label>

                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="Masukkan kata sandi"
                                class="w-full rounded-lg border border-ink-100 bg-white px-3.5 py-2.5 pr-12 text-sm text-ink-900 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-50 dark:placeholder:text-ink-500"
                            >

                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-ink-400 transition hover:text-ink-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500/40 dark:text-ink-500 dark:hover:text-ink-200"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                :aria-pressed="showPassword"
                            >
                                <svg x-show="!showPassword" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"/>
                                    <circle cx="12" cy="12" r="2.75"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6.15A10.7 10.7 0 0 1 12 6c6.25 0 9.75 6 9.75 6a15.8 15.8 0 0 1-2.45 3.1M6.2 6.2C3.65 8.05 2.25 12 2.25 12S5.75 18 12 18c1.35 0 2.57-.28 3.65-.73M9.75 9.75a3.18 3.18 0 0 0-.5 1.7A2.75 2.75 0 0 0 12 14.2c.62 0 1.19-.2 1.65-.54"/>
                                </svg>
                            </button>
                        </div>

                        @error('password')
                            <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                name="remember"
                                class="w-4 h-4 rounded border-ink-300 dark:border-ink-600 dark:bg-ink-800 text-brand-600 focus:ring-brand-500/30"
                            >

                            <span class="text-sm text-ink-600 dark:text-ink-300">
                                Ingat saya
                            </span>
                        </label>
                    </div>

                    <x-button>Masuk</x-button>
                </form>
            </x-card>

            <p class="mt-6 text-center text-xs text-ink-400 dark:text-ink-500">
                Butuh bantuan akses? Hubungi Administrator {{ config('app.name') }}.
            </p>

        </div>
    </div>

</div>
@endsection
