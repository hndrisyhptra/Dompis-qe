@extends('layouts.app')

@section('title', 'Tambah Designator')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah Designator</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Tambahkan item baru ke katalog.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('designators.store') }}" class="space-y-5">
            @csrf

            <x-input name="code" label="Kode" placeholder="mis. M-0001" />
            <x-input name="item_name" label="Nama Item" />
            <x-input name="unit" label="Satuan" placeholder="mis. pcs, meter" />

            <x-select name="designator_type_id" label="Tipe" placeholder="Pilih tipe">
                @foreach ($types as $type)
                    <option value="{{ $type->id_designator_type }}" @selected((int) old('designator_type_id') === $type->id_designator_type)>{{ $type->name }}</option>
                @endforeach
            </x-select>

            <x-select name="designator_category_id" label="Kategori (opsional)" placeholder="Tanpa kategori">
                @foreach ($categories as $category)
                    <option value="{{ $category->id_designator_category }}" @selected((int) old('designator_category_id') === $category->id_designator_category)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('designators.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
