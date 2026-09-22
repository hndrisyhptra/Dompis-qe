@extends('layouts.app')

@section('title', 'Tambah Service Area')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Tambah Service Area</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Workzone / STO — max 20 karakter, unik.</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('service-areas.store') }}" class="space-y-5">
            @csrf

            <x-input name="workzone" label="Workzone" placeholder="mis. SAU" />
            <x-input name="name" label="Nama Service Area" placeholder="mis. SANUR" />

            <x-select name="branch_id" label="Branch" placeholder="Pilih branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id_branch }}" @selected((int) old('branch_id') === $branch->id_branch)>{{ $branch->name }} — {{ $branch->regionRef?->name ?? $branch->region }}</option>
                @endforeach
            </x-select>

            <x-active-select />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan</x-button>
                <a href="{{ route('service-areas.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
