@extends('layouts.app')

@section('title', 'Review Evidence')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <a href="{{ route('evidence-approval.index') }}" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">
            &larr; Kembali
        </a>
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50 mt-2">Review Evidence</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
            LOP {{ $evidence->lop->kode_lop }} — {{ $evidence->lop->nama_lop }}
        </p>
    </div>

    <x-card>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm mb-5">
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Step</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $evidence->step->label() }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Tipe</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $evidence->type->label() }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Item Designator</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $evidence->designator ? "{$evidence->designator->code} — {$evidence->designator->item_name}" : '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Diupload oleh</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $evidence->uploader?->name }} · {{ $evidence->created_at->format('d M Y H:i') }}</dd>
            </div>
            @if ($evidence->note)
                <div class="sm:col-span-2">
                    <dt class="text-ink-500 dark:text-ink-400">Catatan Teknisi</dt>
                    <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $evidence->note }}</dd>
                </div>
            @endif
        </dl>

        @php
            $mime = $evidence->metadata['mime'] ?? null;
            $isImage = $mime && str_starts_with($mime, 'image/');
            $fileUrl = \Illuminate\Support\Facades\Storage::url($evidence->file_path);
        @endphp

        @if ($isImage)
            <img src="{{ $fileUrl }}" alt="Evidence" class="w-full rounded-lg border border-ink-100 dark:border-ink-700">
        @else
            <a href="{{ $fileUrl }}" target="_blank"
               class="inline-block rounded-lg border border-ink-100 dark:border-ink-700 bg-ink-50 dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-100 dark:hover:bg-ink-700 transition">
                Buka File
            </a>
        @endif
    </x-card>

    @if ($evidence->status->value === 'pending')
        <div class="flex flex-col sm:flex-row gap-4">
            <x-card class="flex-1">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-3">Setujui</h2>
                <form method="POST" action="{{ route('evidence-approval.approve', $evidence) }}"
                      onsubmit="return confirm('Setujui evidence ini?');">
                    @csrf
                    <x-button>Setujui Evidence</x-button>
                </form>
            </x-card>

            <x-card class="flex-1">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-3">Tolak</h2>
                <form method="POST" action="{{ route('evidence-approval.reject', $evidence) }}" class="space-y-3">
                    @csrf
                    <x-input name="review_note" label="Alasan Penolakan" />
                    <button type="submit" class="w-full rounded-lg border border-brand-200 dark:border-brand-800 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition">
                        Tolak Evidence
                    </button>
                </form>
            </x-card>
        </div>
    @else
        <x-card>
            <p class="text-sm text-ink-900 dark:text-ink-50">
                Status: <x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge>
            </p>
            @if ($evidence->review_note)
                <p class="text-sm text-ink-600 dark:text-ink-300 mt-2">Catatan: {{ $evidence->review_note }}</p>
            @endif
            <p class="text-xs text-ink-400 dark:text-ink-500 mt-2">
                Direview oleh {{ $evidence->reviewer?->name }} · {{ $evidence->reviewed_at?->format('d M Y H:i') }}
            </p>
        </x-card>
    @endif
</div>
@endsection
