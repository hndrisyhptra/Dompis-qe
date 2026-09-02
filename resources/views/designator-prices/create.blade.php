@extends('layouts.app')

@section('title', 'Tambah KHS')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah KHS</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Set harga satuan sebuah designator di dalam sebuah paket.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('designator-prices.store') }}" class="space-y-5">
            @csrf

            <x-select name="designator_id" label="Designator" placeholder="Pilih designator">
                @foreach ($designators as $designator)
                    <option value="{{ $designator->id_designator }}" @selected(old('designator_id') == $designator->id_designator)>
                        {{ $designator->code }} — {{ $designator->item_name }}
                    </option>
                @endforeach
            </x-select>

            <x-select name="package_id" label="Paket" placeholder="Pilih paket">
                @foreach ($packages as $package)
                    <option value="{{ $package->id_package }}" @selected(old('package_id') == $package->id_package)>{{ $package->name }}</option>
                @endforeach
            </x-select>

            <x-input name="price" label="Harga (Rp)" type="number" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('designator-prices.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
