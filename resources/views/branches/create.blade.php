@extends('layouts.app')

@section('title', 'Tambah Branch')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah Branch</h1>
    </div>

    <x-card>
        <form method="POST" action="{{ route('branches.store') }}" class="space-y-5">
            @csrf

            <x-input name="code" label="Kode" placeholder="mis. SBY" />
            <x-input name="name" label="Nama Branch" placeholder="mis. SURABAYA" />

            <x-select name="region_id" label="Region" placeholder="Pilih region">
                @foreach ($regions as $region)
                    <option value="{{ $region->id_region }}" @selected((int) old('region_id') === $region->id_region)>{{ $region->name }}</option>
                @endforeach
            </x-select>

            <x-active-select />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('branches.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
