@extends('layouts.app')

@section('title', 'KHS')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">KHS</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Harga satuan designator per paket.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('designator-prices.import.form') }}">
                <button type="button" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
                    Import CSV
                </button>
            </a>
            <a href="{{ route('designator-prices.create') }}">
                <x-button type="button" class="!w-auto px-4">+ Tambah KHS</x-button>
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('designator-prices.index') }}" class="flex gap-3 mb-4">
        <select name="package" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <option value="">Semua Paket</option>
            @foreach ($packages as $package)
                <option value="{{ $package->id_package }}" @selected($packageFilter == $package->id_package)>{{ $package->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Filter
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Designator</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Paket</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Harga</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($prices as $price)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">
                        {{ $price->designator->code }}
                        <span class="text-ink-500 dark:text-ink-400 font-normal">— {{ $price->designator->item_name }}</span>
                    </td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $price->package->name }}</td>
                    <td class="px-4 py-3 text-right text-ink-900 dark:text-ink-50">Rp {{ number_format($price->price, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('designator-prices.edit', $price) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <form method="POST" action="{{ route('designator-prices.destroy', $price) }}" class="inline"
                              onsubmit="return confirm('Hapus KHS ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada data KHS.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $prices->links() }}
    </div>
</div>
@endsection
