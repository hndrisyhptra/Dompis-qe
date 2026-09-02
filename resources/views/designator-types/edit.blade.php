@extends('layouts.app')

@section('title', 'Edit Tipe Designator')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit Tipe Designator</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $type->code }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('designator-types.update', $type) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="code" label="Kode" :value="$type->code" />
            <x-input name="name" label="Nama" :value="$type->name" />

            <div>
                <label for="description" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Deskripsi (opsional)</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">{{ old('description', $type->description) }}</textarea>
                @error('description')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
            </div>

            <x-active-select :value="$type->is_active" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('designator-types.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
