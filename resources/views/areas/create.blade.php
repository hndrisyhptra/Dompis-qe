@extends('layouts.app')

@section('title', 'Tambah Area')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah Area</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Kode area unik, mis. 3 untuk Area 3.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('areas.store') }}" class="space-y-5">
            @csrf

            <x-input name="code" label="Kode" placeholder="mis. 3" />
            <x-input name="name" label="Nama Area" placeholder="mis. Area 3" />
            <div>
                <label for="description" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Deskripsi (opsional)</label>
                <textarea id="description" name="description" rows="3" placeholder="Keterangan area..."
                    class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">{{ old('description') }}</textarea>
            </div>
            <x-active-select />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('areas.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
