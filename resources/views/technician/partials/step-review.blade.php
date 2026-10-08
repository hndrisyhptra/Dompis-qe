@php($canReplace = $lop->status_lop === \App\Enums\LopStatus::REJECTED)
@php($reservedIds = $state['items']->pluck('designator_id'))
<section class="mt-5 space-y-4">
    @if ($step === 1)
        <div><p class="text-[10px] font-bold uppercase text-brand-600">Step 1</p><h2 class="mt-1 text-lg font-extrabold">Reservasi material</h2></div>
        @forelse ($state['items'] as $item)
            <article class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-extrabold">{{ $item->designator->code }}</p>
                <p class="mt-1 text-xs leading-5 text-ink-500 dark:text-ink-400">{{ $item->designator->item_name }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-ink-50 p-3 text-xs dark:bg-ink-800">
                    <div><p class="text-ink-400">Reservasi</p><p class="mt-1 font-bold">{{ number_format((float) $item->qty, 0, ',', '.') }} {{ $item->designator->unit }}</p></div>
                    <div><p class="text-ink-400">Terpakai</p><p class="mt-1 font-bold">{{ $item->qty_actual === null ? 'Belum diisi' : number_format((float) $item->qty_actual, 0, ',', '.').' '.$item->designator->unit }}</p></div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-ink-200 bg-white p-5 text-center text-xs text-ink-500 dark:border-ink-700 dark:bg-ink-900">Belum ada reservasi material.</p>
        @endforelse
    @elseif ($step === 2)
        <div><p class="text-[10px] font-bold uppercase text-brand-600">Step 2</p><h2 class="mt-1 text-lg font-extrabold">Evidence Material Tiba</h2></div>
        <x-technician-evidence-uploader :$lop category="material_arrival" title="Material tiba" description="Lihat foto, status review, dan alasan reject." :existing="$evidenceFor('material_arrival')" :allow-upload="false" :allow-replace="$canReplace" />
    @elseif ($step === 3)
        <div><p class="text-[10px] font-bold uppercase text-brand-600">Step 3</p><h2 class="mt-1 text-lg font-extrabold">Evidence Pra</h2></div>
        @if ($lop->program_type->usesProjectStatus())
            <x-technician-evidence-uploader :$lop category="request_letter" type="DOCUMENT" title="Surat Permintaan" description="Dokumen acuan pekerjaan." :existing="$requestLetters" :allow-upload="false" :allow-replace="false" :show-status="false" />
        @endif
        <article class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <h3 class="text-sm font-bold">Tag lokasi pekerjaan</h3>
            @if ($state['survey'])
                <p class="mt-2 text-xs text-ink-500">{{ $state['survey']->latitude }}, {{ $state['survey']->longitude }}</p>
                <a href="https://maps.google.com/?q={{ $state['survey']->latitude }},{{ $state['survey']->longitude }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-brand-50 px-4 py-2 text-xs font-bold text-brand-600 transition hover:bg-brand-100 dark:bg-brand-900/20 dark:text-brand-300 dark:hover:bg-brand-900/40">Buka peta ↗</a>
            @else
                <p class="mt-2 text-xs text-ink-500">Lokasi belum tersedia.</p>
            @endif
        </article>
        <x-technician-evidence-uploader :$lop category="pre" title="Foto sebab/kondisi awal pekerjaan" description="Lihat foto, status review, dan alasan reject." :existing="$evidenceFor('pre')" :allow-upload="false" :allow-replace="$canReplace" />
        <x-technician-evidence-uploader :$lop category="insera" title="Capture Ticket Insera" description="Lihat capture tiket dan status review." :existing="$evidenceFor('insera')" :allow-upload="false" :allow-replace="$canReplace" />
        @foreach ($evidenceFor('before')->groupBy('designator_id') as $group)
            <x-technician-evidence-uploader :$lop category="before" :designator-id="$group->first()->designator_id" :title="'Before · '.($group->first()->designator?->code ?? 'Dokumentasi umum / item lama')" description="Evidence kondisi sebelum pekerjaan." :existing="$group" :allow-upload="false" :allow-replace="$canReplace" />
        @endforeach
    @elseif ($step === 4)
        <div><p class="text-[10px] font-bold uppercase text-brand-600">Step 4</p><h2 class="mt-1 text-lg font-extrabold">Evidence Progress</h2></div>
        @foreach ($state['items'] as $item)
            <x-technician-evidence-uploader :$lop category="progress" :designator-id="$item->designator_id" :title="'Progress · '.$item->designator->code" :description="$item->designator->item_name" :existing="$evidenceFor('progress', $item->designator_id)" :allow-upload="false" :allow-replace="$canReplace" />
        @endforeach
        @foreach ($evidenceFor('progress')->filter(fn ($e) => ! $reservedIds->contains($e->designator_id))->groupBy('designator_id') as $group)
            <x-technician-evidence-uploader :$lop category="progress" :designator-id="$group->first()->designator_id" :title="'Progress · '.($group->first()->designator?->code ?? 'Dokumentasi umum / item lama')" description="Dokumentasi tersimpan di luar item reservasi saat ini." :existing="$group" :allow-upload="false" :allow-replace="$canReplace" />
        @endforeach
    @elseif ($step === 5)
        <div><p class="text-[10px] font-bold uppercase text-brand-600">Step 5</p><h2 class="mt-1 text-lg font-extrabold">Evidence After</h2></div>
        @foreach ($state['items'] as $item)
            <x-technician-evidence-uploader :$lop category="after" :designator-id="$item->designator_id" :title="'After · '.$item->designator->code" :description="$item->designator->item_name" :existing="$evidenceFor('after', $item->designator_id)" :allow-upload="false" :allow-replace="$canReplace" />
        @endforeach
        @foreach ($evidenceFor('after')->filter(fn ($e) => ! $reservedIds->contains($e->designator_id))->groupBy('designator_id') as $group)
            <x-technician-evidence-uploader :$lop category="after" :designator-id="$group->first()->designator_id" :title="'After · '.($group->first()->designator?->code ?? 'Dokumentasi umum / item lama')" description="Dokumentasi tersimpan di luar item reservasi saat ini." :existing="$group" :allow-upload="false" :allow-replace="$canReplace" />
        @endforeach
        <x-technician-evidence-uploader :$lop category="slot_port" title="Slot Port" description="Lihat foto posisi slot port dan status review." :existing="$evidenceFor('slot_port')" :allow-upload="false" :allow-replace="$canReplace" />
        <p class="rounded-2xl border border-ink-100 bg-white p-4 text-xs leading-5 text-ink-500 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-400">{{ $showBoqReview ? 'Klik tombol Review BOQ di bawah informasi LOP untuk melihat rekap pemakaian dan nilai pekerjaan.' : 'Rekap quantity actual belum lengkap. Review BOQ tersedia setelah seluruh actual disimpan pada Step After.' }}</p>
    @endif
</section>
