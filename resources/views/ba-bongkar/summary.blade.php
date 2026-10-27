@php
    $baDocument = \App\Support\BaBongkarRules::document($lop);
    $baForm = data_get($baDocument?->metadata, 'form', data_get($lop->ba_bongkar_data, 'form', []));
@endphp
<article class="space-y-3 rounded-md border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900">
    <div class="flex flex-wrap items-center justify-between gap-2">@if ($showHeading ?? true)<h3 class="text-sm font-bold">BA Bongkar</h3>@endif @if ($baDocument)<x-badge :variant="$baDocument->status->value === 'approved' ? 'success' : ($baDocument->status->value === 'rejected' ? 'danger' : 'warning')">{{ $baDocument->status->label() }}</x-badge>@endif</div>
    <p class="text-sm">{{ $lop->ba_bongkar_required === false ? 'Pekerjaan ini tidak memerlukan BA Bongkar.' : ($lop->ba_bongkar_required === true ? 'Pekerjaan ini memerlukan BA Bongkar.' : 'Teknisi belum menentukan kebutuhan BA Bongkar.') }}</p>
    @if ($baDocument)
        <a href="{{ $baDocument->url() }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-md bg-brand-50 px-3 text-sm font-bold text-brand-700 dark:bg-brand-950 dark:text-brand-300">Lihat / unduh PDF BA Bongkar</a>
        @if ($baDocument->review_note)<p class="rounded-md bg-red-50 p-3 text-sm text-red-700">Alasan penolakan: {{ $baDocument->review_note }}</p>@endif
        <p class="text-xs">Teknisi: <strong>{{ data_get($baDocument->metadata, 'signatures.technician.name', 'Belum TTD') }}</strong></p>
    @elseif ($lop->ba_bongkar_required)
        <p class="text-xs text-amber-700">Draft belum menghasilkan PDF yang siap diajukan.</p>
    @endif
    @if ($baForm)
        <dl class="grid gap-2 text-xs sm:grid-cols-2">@foreach (['date' => 'Tanggal', 'witel' => 'Branch', 'sto_asal' => 'STO asal', 'sto_tujuan' => 'STO tujuan', 'lokasi' => 'Lokasi'] as $key => $label)<div><dt class="text-ink-500">{{ $label }}</dt><dd class="mt-1 break-words font-semibold">{{ $baForm[$key] ?? '—' }}</dd></div>@endforeach</dl>
        @foreach ($baForm['materials'] ?? [] as $material)
            <div class="rounded-md bg-ink-50 p-3 text-xs dark:bg-ink-800"><p class="font-bold">{{ \App\Support\BaBongkarRules::MATERIALS[$material['group']]['label'] ?? '' }} · {{ $material['type'] ?? '' }}</p><p class="mt-1">@foreach (['length' => 'Panjang (m)', 'quantity' => 'Jumlah', 'year' => 'Tahun', 'brand' => 'Merek', 'segments' => 'Jenis', 'condition' => 'Kondisi'] as $key => $label)@if (filled($material[$key] ?? null)) {{ $label }}: {{ $material[$key] }} · @endif @endforeach</p></div>
        @endforeach
        @if (filled($baForm['other_materials'] ?? null))
            <div class="rounded-md bg-ink-50 p-3 text-xs dark:bg-ink-800"><p class="font-bold">Material lainnya</p><p class="mt-1 whitespace-pre-line break-words">{{ $baForm['other_materials'] }}</p></div>
        @endif
    @endif
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach ($lop->evidences->where('category', \App\Enums\EvidenceCategory::BA_BONGKAR_PHOTO) as $photo)<a href="{{ $photo->url() }}" target="_blank" rel="noopener"><img src="{{ $photo->url() }}" loading="lazy" alt="Foto material bongkaran" class="aspect-square w-full rounded-md object-cover"></a>@endforeach</div>
</article>
