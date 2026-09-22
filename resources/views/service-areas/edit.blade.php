@extends('layouts.app')

@section('title', 'Edit Service Area')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Edit Service Area</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">{{ $serviceArea->workzone }} — {{ $serviceArea->name }}</p>
    </div>

    <x-card>
        <form method="POST" action="{{ route('service-areas.update', $serviceArea) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-input name="workzone" label="Workzone" :value="$serviceArea->workzone" />
            <x-input name="name" label="Nama Service Area" :value="$serviceArea->name" />

            <x-select name="branch_id" label="Branch" placeholder="Pilih branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id_branch }}" @selected((int) old('branch_id', $serviceArea->branch_id) === $branch->id_branch)>{{ $branch->name }} — {{ $branch->regionRef?->name ?? $branch->region }}</option>
                @endforeach
            </x-select>

            <x-active-select :value="$serviceArea->is_active" />

            <div class="flex items-center gap-3 pt-2">
                <x-button>Simpan Perubahan</x-button>
                <a href="{{ route('service-areas.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
