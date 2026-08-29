@extends('layouts.app')

@section('title', 'Edit LOP')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit LOP</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $lop->kode_lop }} — {{ $lop->nama_lop }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('lop.update', $lop) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="kode_lop" label="Kode LOP" :value="$lop->kode_lop" />
            <x-input name="nama_lop" label="Nama LOP" :value="$lop->nama_lop" />

            <x-select name="wbs_type" label="WBS Type" placeholder="Pilih jenis WBS">
                @foreach (\App\Enums\WbsType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('wbs_type', $lop->wbs_type->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>

            <x-input name="sto" label="STO (opsional)" :value="$lop->sto" />
            <x-input name="branch" label="Branch (opsional)" :value="$lop->branch" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('lop.show', $lop) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
