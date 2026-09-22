<!doctype html>
<html lang="id"
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
      x-init="$watch('darkMode', v => localStorage.setItem('darkMode', v))"
      :class="{ dark: darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-50 dark:bg-ink-950 font-sans text-ink-800 dark:text-ink-100 antialiased">

    <input type="checkbox" id="nav-toggle" class="peer hidden">

    <label for="nav-toggle"
           class="hidden peer-checked:block lg:hidden fixed inset-0 bg-black/30 z-30"
           aria-hidden="true"></label>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-ink-100/70 bg-white shadow-[4px_0_24px_rgba(16,24,40,0.04)] dark:border-ink-800 dark:bg-ink-900 dark:shadow-none
                  -translate-x-full peer-checked:translate-x-0 transition-transform duration-200
                  lg:translate-x-0">
        <div class="border-b border-ink-100/70 p-5 dark:border-ink-800">
            <x-brand-mark variant="adaptive" />
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1.5">
            @php
                $navDisabled = 'flex items-center justify-between rounded-lg px-3 py-2 text-sm text-ink-400 dark:text-ink-500 cursor-not-allowed';
                $navGroupHeader = 'w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-ink-700 hover:bg-ink-50 hover:text-ink-950 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white transition';
                $navSubLink = 'relative flex items-center gap-2.5 rounded-lg pl-3 pr-3 py-2 text-sm transition';
                $navSubLinkActive = 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300 font-semibold';
                $navSubLinkInactive = 'text-ink-600 hover:bg-ink-50 hover:text-ink-950 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white';
                $navLinkActive = 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300 font-semibold';
                $navLinkInactive = 'text-ink-600 hover:bg-ink-50 hover:text-ink-950 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white';
                $navIcon = 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300';
                $navIconActive = 'bg-brand-100 text-brand-700 dark:bg-brand-950/70 dark:text-brand-300';
                $navDivider = 'border-ink-100 dark:border-ink-700';
                $navBadge = '!border-ink-200 !bg-ink-50 !text-ink-400 dark:!border-ink-700 dark:!bg-ink-800 dark:!text-ink-500';
                $chevron = '<svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="open ? \'rotate-180\' : \'\'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>';
                $navLabel = 'px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-[0.16em] text-ink-400 dark:text-ink-500';
            @endphp

            {{-- ========== OPERASIONAL ========== --}}
            <p class="{{ $navLabel }}">Operasional</p>

            @if (auth()->user()?->hasRole(\App\Enums\UserRole::SUPER_ADMIN, \App\Enums\UserRole::ADMIN))
            <a href="{{ route('dashboard') }}"
               class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('dashboard') ? $navLinkActive : $navLinkInactive }}">
                @if (request()->routeIs('dashboard'))
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                @endif
                <span class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('dashboard') ? $navIconActive : $navIcon }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C6.5 20.496 5.996 21 5.375 21h-2.25A1.125 1.125 0 012 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    </span>
                    Dashboard
                </span>
            </a>
            @else
            <div class="{{ $navDisabled }}">
                <span class="flex items-center gap-2.5">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C6.5 20.496 5.996 21 5.375 21h-2.25A1.125 1.125 0 0 1 2 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/></svg>
                    Dashboard
                </span>
                <x-badge variant="neutral" class="{{ $navBadge }}">Segera</x-badge>
            </div>
            @endif

            @unless (auth()->user()?->hasRole(\App\Enums\UserRole::SUPER_ADMIN))
            <div x-data="{ open: {{ request()->routeIs('lop.*') ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                    <span class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M2.25 13.838V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162a1.13 1.13 0 00-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a1.13 1.13 0 00-.1.661z" />
                        </svg>
                        Inbox
                    </span>
                    {!! $chevron !!}
                </button>
                <div x-show="open" x-transition class="mt-1 ml-4 space-y-1 border-l pl-3 {{ $navDivider }}">
                    <a href="{{ route('lop.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('lop.index') ? $navSubLinkActive : $navSubLinkInactive }}">
                        @if (request()->routeIs('lop.index'))
                            <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                        @endif
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                        Active LOP
                    </a>
                    <a href="{{ route('lop.history') }}" class="{{ $navSubLink }} {{ request()->routeIs('lop.history') ? $navSubLinkActive : $navSubLinkInactive }}">
                        @if (request()->routeIs('lop.history'))
                            <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                        @endif
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                        History
                    </a>
                </div>
            </div>
            @endunless

            @can('create', \App\Models\QeLop::class)
                <a href="{{ route('lop.create') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('lop.create') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('lop.create'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('lop.create') ? $navIconActive : $navIcon }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    Input LOP Baru
                </a>
            @endcan

            @unless (auth()->user()?->hasRole(\App\Enums\UserRole::TEKNISI))
                <div x-data="{ open: {{ request()->routeIs('program.*') ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44Z" />
                            </svg>
                            Program
                        </span>
                        {!! $chevron !!}
                    </button>
                    <div x-show="open" x-transition class="mt-1 ml-4 space-y-1 border-l pl-3 {{ $navDivider }}">
                        <a href="{{ route('program.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('program.index') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('program.index'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            Ringkasan
                        </a>
                        @foreach (\App\Enums\ProgramType::cases() as $type)
                            @php($isActive = request()->routeIs('program.show') && request()->route('program') === $type->value)
                            <a href="{{ route('program.show', $type->value) }}" class="{{ $navSubLink }} {{ $isActive ? $navSubLinkActive : $navSubLinkInactive }}">
                                @if ($isActive)
                                    <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                                @endif
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                                {{ $type->label() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endunless

            {{-- ========== VERIFIKASI ========== --}}
            @if (auth()->user()?->hasPermission('approve_evidence'))
                <p class="{{ $navLabel }} mt-4">Verifikasi</p>
                <a href="{{ route('evidence-approval.index') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('evidence-approval.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('evidence-approval.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('evidence-approval.*') ? $navIconActive : $navIcon }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    Approval Evidence
                </a>
            @endif

            {{-- ========== MASTER ========== --}}
            @if (auth()->user()?->hasPermission('manage_master_data'))
                <p class="{{ $navLabel }} mt-4">Master</p>
                <div x-data="{ open: {{ request()->routeIs(['designators.*', 'designator-prices.*', 'packages.*', 'ticket-segment-maps.*']) ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9Z" />
                            </svg>
                            Master Designator
                        </span>
                        {!! $chevron !!}
                    </button>
                    <div x-show="open" x-transition class="mt-1 ml-4 space-y-1 border-l pl-3 {{ $navDivider }}">
                        <a href="{{ route('designators.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('designators.*') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('designators.*'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            Designator
                        </a>
                        <a href="{{ route('designator-prices.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('designator-prices.*') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('designator-prices.*'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            KHS
                        </a>
                        <a href="{{ route('packages.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('packages.*') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('packages.*'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            Paket KHS
                        </a>
                        <a href="{{ route('ticket-segment-maps.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('ticket-segment-maps.*') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('ticket-segment-maps.*'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            Pemetaan Segment Tiket
                        </a>
                    </div>
                </div>

                <div x-data="{ open: {{ request()->routeIs(['master-data.*', 'regions.*', 'branches.*', 'service-areas.*', 'designator-categories.*', 'designator-types.*']) ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                            Master Data
                        </span>
                        {!! $chevron !!}
                    </button>
                    <div x-show="open" x-transition class="mt-1 ml-4 space-y-1 border-l pl-3 {{ $navDivider }}">
                        @foreach ([
                            'master-data.index' => 'Ringkasan',
                            'regions.index' => 'Region',
                            'branches.index' => 'Branch',
                            'service-areas.index' => 'Service Area',
                            'designator-categories.index' => 'Kategori Designator',
                            'designator-types.index' => 'Tipe Designator',
                        ] as $routeName => $label)
                            @php($group = \Illuminate\Support\Str::before($routeName, '.').'.*')
                            <a href="{{ route($routeName) }}" class="{{ $navSubLink }} {{ request()->routeIs($group) ? $navSubLinkActive : $navSubLinkInactive }}">
                                @if (request()->routeIs($group))
                                    <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                                @endif
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ========== PENGATURAN ========== --}}
            @can('manage-master-data')
                <p class="{{ $navLabel }} mt-4">Pengaturan</p>
                <a href="{{ route('lop-name-format.edit') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('lop-name-format.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('lop-name-format.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('lop-name-format.*') ? $navIconActive : $navIcon }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.594c.55 0 1.02.398 1.11.94l.213 1.281c.062.374.312.686.644.87a6.52 6.52 0 01.22.127c.324.196.72.257 1.076.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.004-.827c-.292-.24-.437-.613-.43-.992a6.75 6.75 0 010-.994c-.007-.379.138-.75.43-.99l1.005-.828a1.125 1.125 0 01.26-1.43l-1.297-2.247a1.125 1.125 0 01-1.37-.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.582.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.869a6.52 6.52 0 01-.22-.127c-.324-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.37-.49l-1.296-2.247a1.125 1.125 0 01.26-1.431l1.003-.827c.293-.24.438-.613.431-.991a6.75 6.75 0 010-1.005c.007-.378-.138-.75-.431-.991l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.296-2.247a1.125 1.125 0 011.37-.491l1.216.457c.355.133.75.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    Format Nama LOP
                </a>
            @endcan

            {{-- ========== SISTEM ========== --}}
            @if (auth()->user()?->hasPermission('manage_users'))
                <p class="{{ $navLabel }} mt-4">Sistem</p>
                <a href="{{ route('users.index') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('users.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('users.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('users.*') ? $navIconActive : $navIcon }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    User Management
                </a>
            @endif
        </nav>

        <div class="border-t border-ink-100/70 p-4 text-xs text-ink-400 dark:border-ink-800 dark:text-ink-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 bg-white dark:bg-ink-900 border-b border-ink-100 dark:border-ink-700">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 py-3">
                <label for="nav-toggle" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-ink-50 dark:hover:bg-ink-800 cursor-pointer">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink-700 dark:text-ink-200" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </label>

                <div class="flex-1"></div>

                <div class="flex items-center gap-3">
                    <button type="button" @click="darkMode = !darkMode"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-ink-50 dark:hover:bg-ink-800 transition"
                            title="Ganti tema">
                        <svg x-show="!darkMode" viewBox="0 0 24 24" class="w-5 h-5 text-ink-600" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        <svg x-show="darkMode" viewBox="0 0 24 24" class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                    </button>

                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium text-ink-900 dark:text-ink-50 leading-none">{{ auth()->user()?->name }}</p>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">{{ auth()->user()?->role?->name ?? '—' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-brand-600 dark:hover:text-brand-400 transition">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <x-toast />

        <main class="p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</body>
</html>
