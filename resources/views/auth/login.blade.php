@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="min-h-screen flex flex-col lg:flex-row">

    {{-- LEFT SECTION — brand & context (hidden di mobile, full di desktop) --}}
    <div class="hidden lg:flex lg:w-1/2 bg-ink-900 relative overflow-hidden flex-col justify-between p-12 xl:p-16">
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;"></div>

        <x-brand-mark variant="dark" class="relative z-10" />

        <div class="relative z-10 max-w-md">
            <h1 class="text-3xl xl:text-4xl font-bold text-white leading-tight tracking-tight">
                Platform Operasional QE Terpadu
            </h1>
            <p class="mt-4 text-ink-400 text-base leading-relaxed">
                Kelola LOP, assignment teknisi, survey lapangan, evidence, dan approval
                dalam satu sistem — dari input hingga penyelesaian pekerjaan.
            </p>

            <div class="mt-10 grid grid-cols-2 gap-4">
                <div class="border border-white/10 rounded-xl p-4 bg-white/[0.03]">
                    <p class="text-2xl font-bold text-white">Recovery</p>
                    <p class="text-ink-400 text-xs mt-1">QE Recovery Program</p>
                </div>
                <div class="border border-white/10 rounded-xl p-4 bg-white/[0.03]">
                    <p class="text-2xl font-bold text-white">Preventive</p>
                    <p class="text-ink-400 text-xs mt-1">QE Preventive Program</p>
                </div>
            </div>

            {{-- Ilustrasi sederhana: field operation / network quality, garis line-art --}}
            <div class="mt-10">
                <svg viewBox="0 0 400 160" class="w-full h-auto opacity-90">
                    <line x1="20" y1="140" x2="380" y2="140" stroke="#4a5567" stroke-width="1.5"/>
                    <g stroke="#dc4e57" stroke-width="2" fill="none">
                        <line x1="70" y1="140" x2="70" y2="60"/>
                        <line x1="55" y1="75" x2="85" y2="75"/>
                        <line x1="60" y1="90" x2="80" y2="90"/>
                        <line x1="65" y1="105" x2="75" y2="105"/>
                    </g>
                    <circle cx="70" cy="55" r="4" fill="#dc4e57"/>
                    <path d="M180 140 L210 90 L230 120 L260 70 L280 100 L320 60"
                          stroke="#9aa4b1" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="320" cy="60" r="5" fill="#ea8288"/>
                    <g stroke="#4a5567" stroke-width="1.5" fill="none">
                        <path d="M340 55 a10 10 0 0 1 0 -14"/>
                        <path d="M345 58 a17 17 0 0 1 0 -20"/>
                    </g>
                </svg>
            </div>
        </div>

        <p class="relative z-10 text-ink-500 text-xs">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Internal application — akses terbatas untuk personel resmi.
        </p>
    </div>

    {{-- RIGHT SECTION — login card --}}
    <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10">

        <x-brand-mark variant="light" class="lg:hidden mb-8" />

        <div class="w-full max-w-sm">
            <x-card>
                <h2 class="text-xl font-bold text-ink-900 dark:text-ink-50">Masuk ke akun Anda</h2>
                <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Gunakan NIK dan kata sandi yang terdaftar.</p>

                @if ($errors->any())
                    <div class="mt-5 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 px-4 py-3">
                        <p class="text-sm text-brand-700 dark:text-brand-300 font-medium">
                            {{ $errors->first() }}
                        </p>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                        <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">{{ session('status') }}</p>
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

                    <x-input
                        name="password"
                        type="password"
                        label="Kata Sandi"
                        placeholder="Masukkan kata sandi"
                        autocomplete="current-password"
                    />

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="remember"
                                   class="w-4 h-4 rounded border-ink-300 dark:border-ink-600 dark:bg-ink-800 text-brand-600 focus:ring-brand-500/30">
                            <span class="text-sm text-ink-600 dark:text-ink-300">Ingat saya</span>
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
