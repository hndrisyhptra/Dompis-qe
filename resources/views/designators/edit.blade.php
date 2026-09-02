@extends('layouts.app')

@section('title', 'Edit Designator')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit Designator</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $designator->code }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('designators.update', $designator) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="code" label="Kode" :value="$designator->code" />
            <x-input name="item_name" label="Nama Item" :value="$designator->item_name" />
            <x-input name="unit" label="Satuan" :value="$designator->unit" />

<<<<<<< HEAD
            <x-select name="designator_type_id" label="Tipe" placeholder="Pilih tipe">
                @foreach ($types as $type)
                    <option value="{{ $type->id_designator_type }}" @selected((int) old('designator_type_id', $designator->designator_type_id) === $type->id_designator_type)>{{ $type->name }}</option>
                @endforeach
            </x-select>

            <x-select name="designator_category_id" label="Kategori (opsional)" placeholder="Tanpa kategori">
                @foreach ($categories as $category)
                    <option value="{{ $category->id_designator_category }}" @selected((int) old('designator_category_id', $designator->designator_category_id) === $category->id_designator_category)>{{ $category->name }}</option>
=======
            <x-select name="type" label="Tipe" placeholder="Pilih tipe">
                @foreach (\App\Enums\DesignatorType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $designator->type->value) === $type->value)>{{ $type->label() }}</option>
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
                @endforeach
            </x-select>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('designators.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
