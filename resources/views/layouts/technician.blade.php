<!doctype html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
      x-init="$watch('darkMode', value => localStorage.setItem('darkMode', value))"
      :class="{ dark: darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#861621">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>if (localStorage.getItem('darkMode') === 'true') document.documentElement.classList.add('dark')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-50 dark:bg-ink-950 font-sans text-ink-900 dark:text-ink-50 antialiased">
    <div class="mx-auto min-h-screen max-w-lg bg-ink-50 dark:bg-ink-950 shadow-2xl shadow-ink-900/5">
        <header class="sticky top-0 z-30 border-b border-ink-100/80 dark:border-ink-800 bg-white/95 dark:bg-ink-900/95 backdrop-blur">
            <div class="flex h-16 items-center justify-between px-4">
                <div class="flex min-w-0 items-center gap-3"> <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white p-1.5 shadow-sm dark:bg-ink-800"> <img src="{{ asset('images/logo-dompis-qe.webp') }}" alt="Dompis QE" class="h-full w-full object-contain" > </div>

                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-400">
                            Dompis QE Field
                        </p>
                        <p class="truncate text-sm font-bold text-ink-900 dark:text-white">
                            @yield('header', 'Workspace Teknisi')
                        </p>
                    </div>

                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="darkMode = !darkMode"
                            class="grid h-10 w-10 place-items-center rounded-full bg-ink-50 text-ink-600 dark:bg-ink-800 dark:text-amber-400"
                            aria-label="Ganti tema">
                        <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                        <svg x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                    </button>
                    <a href="{{ route('technician.notifications') }}" class="relative grid h-10 w-10 place-items-center rounded-full bg-ink-50 text-ink-600 dark:bg-ink-800 dark:text-ink-300" aria-label="Notifikasi">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                        @if (auth()->user()->unreadNotifications()->count())
                            <span class="absolute right-1 top-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-brand-500 dark:border-ink-900"></span>
                        @endif
                    </a>
                </div>
            </div>
        </header>

        <x-toast />

        @if ($errors->any())
            <div class="mx-4 mt-4 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-700 dark:border-brand-900 dark:bg-brand-900/20 dark:text-brand-300">
                <p class="font-bold">Ada data yang perlu diperbaiki</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <main class="px-4 pb-32 pt-5">@yield('content')</main>

        <nav class="fixed inset-x-0 bottom-0 z-40 mx-auto max-w-lg border-t border-ink-100 bg-white/95 px-2 pb-[max(.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur dark:border-ink-800 dark:bg-ink-900/95">
            <div class="grid grid-cols-5 items-end">
                @php
                    $nav = 'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold transition';
                    $active = 'text-brand-600 dark:text-brand-400';
                    $idle = 'text-ink-400 hover:text-ink-700 dark:text-ink-500 dark:hover:text-ink-200';
                @endphp
                <a href="{{ route('technician.dashboard') }}" class="{{ $nav }} {{ request()->routeIs('technician.dashboard') ? $active : $idle }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                    Home
                </a>
                <a href="{{ route('technician.inbox') }}" class="{{ $nav }} {{ request()->routeIs('technician.inbox') ? $active : $idle }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M2.25 13.838V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162"/></svg>
                    Inbox
                </a>
                <a href="{{ route('technician.inbox') }}" class="-mt-7 flex flex-col items-center gap-1 text-[10px] font-bold text-brand-700 dark:text-brand-300">
                    <span class="grid h-14 w-14 place-items-center rounded-full border-4 border-white bg-brand-600 text-white shadow-lg shadow-brand-600/30 dark:border-ink-900">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm9.75 0a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75 0a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                    </span>
                    Project
                </a>
                <a href="{{ route('technician.notifications') }}" class="{{ $nav }} {{ request()->routeIs('technician.notifications*') ? $active : $idle }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31"/></svg>
                    Notif
                </a>
                <a href="{{ route('technician.profile') }}" class="{{ $nav }} {{ request()->routeIs('technician.profile') ? $active : $idle }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    Profile
                </a>
            </div>
        </nav>
    </div>
</body>
</html>
