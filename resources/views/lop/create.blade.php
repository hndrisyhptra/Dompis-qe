@extends('layouts.app')

@section('title', 'Buat LOP')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Buat LOP Baru</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Isi data LOP sesuai jenis pekerjaan.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('lop.store') }}" class="space-y-5">
            @csrf

            <x-input name="kode_lop" label="Kode LOP" placeholder="mis. LOP-001" />
            <x-input name="nama_lop" label="Nama LOP" placeholder="mis. Recovery Jl. Merdeka" />

            <x-select name="wbs_type" label="WBS Type" placeholder="Pilih jenis WBS">
                @foreach (\App\Enums\WbsType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('wbs_type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>

            <x-input name="sto" label="STO (opsional)" />
            <x-input name="branch" label="Branch (opsional)" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('lop.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
