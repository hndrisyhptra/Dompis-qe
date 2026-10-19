<section class="overflow-hidden rounded-md border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
    <div class="space-y-3 border-b border-ink-100 p-4 dark:border-ink-800">
        <div><h2 class="text-sm font-bold">Detail per Baris</h2><p class="mt-1 text-xs leading-5 text-ink-500">Nomor baris mengikuti file asli. Perbaiki penyebabnya, lalu upload ulang hanya baris yang gagal; LOP berhasil tidak perlu diupload ulang.</p></div>
        <nav aria-label="Filter hasil LOP" class="flex flex-wrap gap-2">
            @foreach (['all' => 'Semua hasil', 'failed' => 'Gagal ('.$batch->failed_rows.')', 'success' => 'Berhasil ('.$batch->success_rows.')'] as $key => $label)
                <a href="{{ route('imports.show', [$batch, 'row_status' => $key], false) }}" @if($rowStatus === $key) aria-current="page" @endif class="rounded-md border px-3 py-2 text-xs font-bold {{ $rowStatus === $key ? 'border-brand-600 bg-brand-50 text-brand-700 dark:bg-brand-950/30 dark:text-brand-300' : 'border-ink-200 text-ink-500 dark:border-ink-700' }}">{{ $label }}</a>
            @endforeach
        </nav>
        @if ($batch->isFinished() && $storedRowCount < max($batch->total_rows, $batch->success_rows + $batch->failed_rows))
            <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Sebagian baris belum memiliki rincian hasil karena proses terhenti atau detail batch lama tidak tersimpan. Penyebab baris tersebut tidak dapat direkonstruksi dari jumlah total saja. Periksa Data LOP sebelum mengupload ulang.</p>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
            <thead class="bg-ink-50 text-left text-[11px] uppercase text-ink-500 dark:bg-ink-950/50"><tr><th class="px-4 py-3">Baris</th><th class="px-4 py-3">Incident / LOP</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Penyebab / Keterangan</th></tr></thead>
            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                @forelse ($rows as $row)
                    <tr class="align-top">
                        <td class="px-4 py-4 font-mono text-xs">{{ $row->row_number }}</td>
                        <td class="max-w-sm px-4 py-4"><p class="text-xs font-bold">{{ $row->reference ?: 'Incident belum dibuat' }}</p><p class="mt-1 break-words text-xs text-ink-500">{{ data_get($row->result, 'nama_lop') ?: data_get($row->payload, 'nama_lop') ?: 'Nama LOP otomatis' }}</p><p class="mt-1 text-[10px] text-ink-400">STO: {{ data_get($row->payload, 'sto') ?: 'Kosong' }} · Program: {{ data_get($row->payload, 'program_type') ?: 'Kosong' }}</p></td>
                        <td class="px-4 py-4"><x-badge :variant="$row->status === 'success' ? 'success' : 'danger'">{{ $row->status === 'success' ? 'Berhasil' : 'Gagal' }}</x-badge></td>
                        <td class="min-w-64 max-w-xl px-4 py-4 text-xs leading-5">
                            @if ($row->status === 'failed' && data_get($row->result, 'errors'))
                                <ul class="list-disc space-y-1 pl-4 text-red-700 dark:text-red-300">@foreach (collect(data_get($row->result, 'errors'))->flatten() as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                            @else
                                <p class="{{ $row->status === 'failed' ? 'text-red-700 dark:text-red-300' : 'text-ink-500' }}">{{ $row->message ?: ($row->status === 'failed' ? 'Batch lama tidak menyimpan rincian penyebab. Hubungi admin dengan ID batch dan nomor baris ini.' : 'LOP berhasil dibuat.') }}</p>
                            @endif
                            @if ($row->status === 'failed')<p class="mt-2 text-ink-500">{{ data_get($row->result, 'suggestion') ?: 'Periksa data baris ini dan referensi pada template sebelum mencoba ulang.' }}</p>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-xs text-ink-500">{{ $rowStatus === 'failed' && $batch->failed_rows === 0 ? 'Tidak ada baris gagal yang tercatat. Jika batch gagal sebelum pembacaan baris, lihat pesan kegagalan file di atas.' : 'Tidak ada rincian pada filter ini. Jika proses berhenti sebelum membaca baris, lihat pesan kegagalan file di atas.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
