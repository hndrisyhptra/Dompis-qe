@extends('layouts.app')

@section('title', 'Edit Region')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit Region</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $region->code }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('regions.update', $region) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="code" label="Kode" :value="$region->code" />
            <x-input name="name" label="Nama Region" :value="$region->name" />
            <x-active-select :value="$region->is_active" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('regions.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
