@extends('layouts.technician')

@section('title', 'Profile')
@section('header', 'Profile')

@section('content')
<section class="rounded-3xl bg-ink-900 p-5 text-white">
    <div class="flex items-center gap-4">
        <div class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-600 text-xl font-extrabold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
        <div class="min-w-0"><h1 class="truncate text-lg font-extrabold">{{ $user->name }}</h1><p class="mt-1 text-xs text-ink-300">{{ $user->role?->name }} · {{ $user->branch?->name ?? 'Branch belum diatur' }}</p></div>
    </div>
</section>
<section class="mt-5 overflow-hidden rounded-2xl border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
    @foreach ([['Username / NIK', $user->username], ['NIK', $user->nik ?: '—'], ['Nomor telepon', $user->phone ?: '—'], ['Email', $user->email ?: '—'], ['Login terakhir', $user->last_login_at?->format('d M Y H:i') ?: 'Belum pernah']] as [$label, $value])
        <div class="border-b border-ink-100 px-4 py-3.5 last:border-0 dark:border-ink-800"><p class="text-[10px] font-bold uppercase tracking-wide text-ink-400">{{ $label }}</p><p class="mt-1 text-sm font-semibold">{{ $value }}</p></div>
    @endforeach
</section>
<form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf
    <button class="min-h-12 w-full rounded-2xl border border-brand-200 bg-white text-sm font-bold text-brand-600 dark:border-brand-900 dark:bg-ink-900 dark:text-brand-400">Keluar dari akun</button>
</form>
@endsection
