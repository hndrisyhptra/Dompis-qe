<!doctype html>
<html lang="id"
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
      x-init="$watch('darkMode', v => localStorage.setItem('darkMode', v))"
      :class="{ dark: darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Set kelas dark sebelum CSS/Alpine load, supaya tidak "kedip" ke
         light dulu baru gelap saat preferensi user sudah dark. --}}
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

    {{-- Toggle checkbox murni CSS untuk sidebar mobile - tidak perlu JS/Alpine. --}}
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
                $chevron = '<svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="open ? \'rotate-180\' : \'\'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>';
            @endphp

            @if (auth()->user()?->hasRole(\App\Enums\UserRole::SUPER_ADMIN, \App\Enums\UserRole::ADMIN))
            <a href="{{ route('dashboard') }}"
               class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('dashboard') ? $navLinkActive : $navLinkInactive }}">
                @if (request()->routeIs('dashboard'))
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                @endif
                <span class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('dashboard') ? $navIconActive : $navIcon }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C6.5 20.496 5.996 21 5.375 21h-2.25A1.125 1.125 0 012 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    </span>
                    Dashboard
                </span>
            </a>
            @else
            <div class="{{ $navDisabled }}">
                <span class="flex items-center gap-2.5">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C6.5 20.496 5.996 21 5.375 21h-2.25A1.125 1.125 0 0 1 2 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/></svg>
                    Dashboard
                </span>
                <x-badge variant="neutral" class="{{ $navBadge }}">Segera</x-badge>
            </div>
            @endif

            @can('create', \App\Models\QeLop::class)
                <a href="{{ route('lop.create') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('lop.create') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('lop.create'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('lop.create') ? $navIconActive : $navIcon }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    Input LOP Baru
                </a>
            @endcan

            {{-- Inbox operasional hanya untuk role selain Super Admin. --}}
            @unless (auth()->user()?->hasRole(\App\Enums\UserRole::SUPER_ADMIN))
            <div x-data="{ open: {{ request()->routeIs('lop.*') ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                    <span class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

            @can('manage-master-data')
                <a href="{{ route('lop-name-format.edit') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('lop-name-format.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('lop-name-format.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('lop-name-format.*') ? $navIconActive : $navIcon }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75m-9.75 6h9.75M3.75 6h.008v.008H3.75V6zm0 6h.008v.008H3.75V12zm0 6h.008v.008H3.75V18z" /></svg>
                    </div>
                    Format Nama LOP
                </a>
            @endcan

            {{-- WBS: pemetaan / bucket LOP per jenis WBS. Teknisi diarahkan ke inbox-nya. --}}
            @unless (auth()->user()?->hasRole(\App\Enums\UserRole::TEKNISI))
                <div x-data="{ open: {{ request()->routeIs('wbs.*') ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                            </svg>
                            WBS
                        </span>
                        {!! $chevron !!}
                    </button>
                    <div x-show="open" x-transition class="mt-1 ml-4 space-y-1 border-l pl-3 {{ $navDivider }}">
                        <a href="{{ route('wbs.index') }}" class="{{ $navSubLink }} {{ request()->routeIs('wbs.index') ? $navSubLinkActive : $navSubLinkInactive }}">
                            @if (request()->routeIs('wbs.index'))
                                <span class="absolute -left-3 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                            @endif
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                            Ringkasan
                        </a>
                        @foreach (\App\Enums\WbsType::cases() as $type)
                            @php($isActive = request()->routeIs('wbs.show') && request()->route('wbs') === $type->value)
                            <a href="{{ route('wbs.show', $type->value) }}" class="{{ $navSubLink }} {{ $isActive ? $navSubLinkActive : $navSubLinkInactive }}">
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

            {{-- Master Designator: khusus role dengan permission manage_master_data
                 (saat ini cuma SUPER_ADMIN). --}}
            @if (auth()->user()?->hasPermission('manage_master_data'))
                <div x-data="{ open: {{ request()->routeIs(['designators.*', 'designator-prices.*', 'packages.*', 'ticket-segment-maps.*']) ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
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
            @endif

            {{-- Master Data: reference data inti (Region, Branch, Kategori/Tipe Designator). --}}
            @if (auth()->user()?->hasPermission('manage_master_data'))
                <div x-data="{ open: {{ request()->routeIs(['master-data.*', 'regions.*', 'branches.*', 'designator-categories.*', 'designator-types.*']) ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $navGroupHeader }}">
                        <span class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m5.25 3.75h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
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

            @if (auth()->user()?->hasPermission('approve_evidence'))
                <a href="{{ route('evidence-approval.index') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('evidence-approval.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('evidence-approval.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('evidence-approval.*') ? $navIconActive : $navIcon }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    Approval Evidence
                </a>
            @endif

            @if (auth()->user()?->hasPermission('manage_users'))
                <a href="{{ route('users.index') }}"
                   class="relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ request()->routeIs('users.*') ? $navLinkActive : $navLinkInactive }}">
                    @if (request()->routeIs('users.*'))
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-brand-400"></span>
                    @endif
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('users.*') ? $navIconActive : $navIcon }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                    <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink-700 dark:text-ink-200" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </label>

                <div class="flex-1"></div>

                <div class="flex items-center gap-3">
                    <button type="button" @click="darkMode = !darkMode"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-ink-50 dark:hover:bg-ink-800 transition"
                            title="Ganti tema">
                        <svg x-show="!darkMode" viewBox="0 0 24 24" class="w-5 h-5 text-ink-600" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        <svg x-show="darkMode" viewBox="0 0 24 24" class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2">
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
