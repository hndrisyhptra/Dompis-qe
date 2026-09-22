@extends('layouts.app')

@section('title', 'Service Area')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Service Area</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Manage workzone / SA sesuai dengan branch dan region</p>
        </div>
        <a href="{{ route('service-areas.create') }}">
            <x-button type="button" class="w-auto! px-4">+ Tambah Service Area</x-button>
        </a>
    </div>

    <form method="GET" action="{{ route('service-areas.index') }}" class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari workzone atau nama service area..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
        <select name="branch" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3 py-2.5 text-sm">
            <option value="">Semua Branch</option>
            @foreach ($branches as $b)<option value="{{ $b->name }}" @selected($branchFilter===$b->name)>{{ $b->name }}</option>@endforeach
        </select>
        <select name="region" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3 py-2.5 text-sm">
            <option value="">Semua Region</option>
            @foreach ($regions as $r)<option value="{{ $r->name }}" @selected($regionFilter===$r->name)>{{ $r->name }}</option>@endforeach
        </select>
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">Cari</button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Workzone</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Service Area</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Branch</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Region</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($serviceAreas as $sa)
                <tr>
                    <td class="px-4 py-3 font-mono text-sm font-bold text-ink-900 dark:text-ink-50">{{ $sa->workzone }}</td>
                    <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300">{{ $sa->name }}</td>
                    <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300">{{ $sa->branch?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300">{{ $sa->region?->name ?? $sa->branch?->regionRef?->name ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge :variant="$sa->is_active ? 'success' : 'neutral'">{{ $sa->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('service-areas.edit', $sa) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <form method="POST" action="{{ route('service-areas.destroy', $sa) }}" class="inline" onsubmit="return confirm('Hapus service area ini?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 font-medium">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-ink-400">Belum ada service area.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">{{ $serviceAreas->links() }}</div>
</div>
@endsection
