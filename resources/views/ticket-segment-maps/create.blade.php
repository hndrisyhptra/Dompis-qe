@extends('layouts.app')

@section('title', 'Tambah Pemetaan Segment Tiket')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah Pemetaan Segment Tiket</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Nilai jenis_tiket_2 disimpan dalam huruf kapital.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('ticket-segment-maps.store') }}" class="space-y-5">
            @csrf

            <x-input name="source_value" label="Jenis Tiket 2" placeholder="mis. GAMAS ODP" />

            <x-select name="segment" label="Segmen" placeholder="Pilih segmen jaringan">
                @foreach ($segments as $segment)
                    <option value="{{ $segment->value }}" @selected(old('segment') === $segment->value)>{{ $segment->label() }}</option>
                @endforeach
            </x-select>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('ticket-segment-maps.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
