@extends('layouts.app')

@section('title', 'Edit Paket KHS')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit Paket KHS</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $package->code }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('packages.update', $package) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="code" label="Kode" :value="$package->code" />
            <x-input name="name" label="Nama Paket" :value="$package->name" />

            <div>
                <label for="description" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Deskripsi (opsional)</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">{{ old('description', $package->description) }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('packages.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
