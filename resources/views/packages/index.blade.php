@extends('layouts.app')

@section('title', 'Paket KHS')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Paket KHS</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Wadah harga satuan (lihat menu KHS untuk detail harga per item).</p>
        </div>
        <a href="{{ route('packages.create') }}">
            <x-button type="button" class="!w-auto px-4">+ Tambah Paket</x-button>
        </a>
    </div>

    <form method="GET" action="{{ route('packages.index') }}" class="flex gap-3 mb-4">
        <input
            type="text"
            name="q"
            value="{{ $q }}"
            placeholder="Cari kode atau nama paket..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
        >
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Cari
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama Paket</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Deskripsi</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($packages as $package)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $package->code }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $package->name }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $package->description ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('packages.edit', $package) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <form method="POST" action="{{ route('packages.destroy', $package) }}" class="inline"
                              onsubmit="return confirm('Hapus paket ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada paket.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $packages->links() }}
    </div>
</div>
@endsection
