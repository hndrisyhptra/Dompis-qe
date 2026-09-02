@extends('layouts.app')

@section('title', 'Import Designator')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Import Designator</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
<<<<<<< HEAD
            Upload file CSV dengan kolom: <code class="text-xs bg-ink-100 dark:bg-ink-800 px-1.5 py-0.5 rounded">code, item_name, unit, type, category</code>.
            <span class="block mt-1 text-xs">Kolom <code>type</code> = kode/nama Tipe Designator (mis. <code>MATERIAL</code>). Kolom <code>category</code> opsional (kode/nama Kategori Designator).</span>
=======
            Upload file CSV dengan kolom: <code class="text-xs bg-ink-100 dark:bg-ink-800 px-1.5 py-0.5 rounded">code, item_name, unit, type</code>
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        </p>
    </div>

    @if ($errors->has('file'))
        <div class="mb-4 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 px-4 py-3">
            <p class="text-sm text-brand-700 dark:text-brand-300 font-medium mb-1">Import gagal, tidak ada data yang disimpan:</p>
            <ul class="text-sm text-brand-700 dark:text-brand-300 list-disc list-inside space-y-0.5">
                @foreach ($errors->get('file') as $fileErrors)
                    @foreach ((array) $fileErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('designators.import') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label for="file" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">File CSV</label>
                <input
                    type="file"
                    id="file"
                    name="file"
                    accept=".csv,.txt"
                    class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
                >
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-button>Upload &amp; Import</x-button>
                <a href="{{ route('designators.index') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
