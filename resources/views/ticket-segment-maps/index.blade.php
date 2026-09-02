@extends('layouts.app')

@section('title', 'Pemetaan Segment Tiket')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Pemetaan Segment Tiket</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Menentukan Segmen LOP otomatis dari nilai <span class="font-mono">jenis_tiket_2</span> saat Input LOP Baru.</p>
        </div>
        <a href="{{ route('ticket-segment-maps.create') }}">
            <x-button type="button" class="!w-auto px-4">+ Tambah Pemetaan</x-button>
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('ticket-segment-maps.index') }}" class="flex gap-3 mb-4">
        <input
            type="text"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nilai jenis_tiket_2..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
        >
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Cari
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Jenis Tiket 2</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Segmen</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($maps as $map)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium font-mono">{{ $map->source_value }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $map->segment?->label() ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('ticket-segment-maps.edit', $map) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <form method="POST" action="{{ route('ticket-segment-maps.destroy', $map) }}" class="inline"
                              onsubmit="return confirm('Hapus pemetaan ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada pemetaan.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $maps->links() }}
    </div>
</div>
@endsection
