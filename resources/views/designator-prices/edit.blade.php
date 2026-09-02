@extends('layouts.app')

@section('title', 'Edit KHS')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit KHS</h1>
    </div>

    <x-card>
        <form method="POST" action="{{ route('designator-prices.update', $price) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-select name="designator_id" label="Designator" placeholder="Pilih designator">
                @foreach ($designators as $designator)
                    <option value="{{ $designator->id_designator }}" @selected(old('designator_id', $price->designator_id) == $designator->id_designator)>
                        {{ $designator->code }} — {{ $designator->item_name }}
                    </option>
                @endforeach
            </x-select>

            <x-select name="package_id" label="Paket" placeholder="Pilih paket">
                @foreach ($packages as $package)
                    <option value="{{ $package->id_package }}" @selected(old('package_id', $price->package_id) == $package->id_package)>{{ $package->name }}</option>
                @endforeach
            </x-select>

            <x-input name="price" label="Harga (Rp)" type="number" :value="$price->price" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('designator-prices.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
