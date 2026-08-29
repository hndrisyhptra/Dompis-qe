@extends('layouts.app')

@section('title', 'Active LOP')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Inbox — Active LOP</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">LOP yang sedang berjalan (belum selesai/ditolak).</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('lop.history') }}" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">
                Lihat History
            </a>
            @can('create', \App\Models\QeLop::class)
                <a href="{{ route('lop.create') }}">
                    <x-button type="button" class="!w-auto px-4">+ Buat LOP</x-button>
                </a>
            @endcan
        </div>
    </div>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Kode LOP</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama LOP</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">WBS</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Teknisi</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($lops as $lop)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $lop->kode_lop }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $lop->nama_lop }}</td>
                    <td class="px-4 py-3">
                        <x-badge variant="neutral">{{ $lop->wbs_type->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $lop->activeAssignment?->technician?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('lop.show', $lop) }}" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">
                            Detail
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada LOP aktif.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $lops->links() }}
    </div>
</div>
@endsection
