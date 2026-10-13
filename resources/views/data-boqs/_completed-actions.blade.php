<x-table-action label="Terkunci" disabled>
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
</x-table-action>
<x-table-action label="Reset LOP untuk edit BOQ" tone="info" onclick="document.getElementById('reset-boq-{{ $resetLop->getKey() }}').showModal()">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>
</x-table-action>
<x-modal :id="'reset-boq-'.$resetLop->getKey()" title="Reset LOP Completed">
    <div class="space-y-3 text-left text-sm">
        <p class="font-bold text-ink-900 dark:text-white">{{ $resetLop->incident }} · {{ $resetLop->nama_lop }}</p>
        <p class="text-ink-600 dark:text-ink-300">LOP akan kembali ke <strong>Progress</strong> agar BOQ dapat diedit. Foto, keputusan review, penugasan, dan rekap aktual tidak dihapus.</p>
        <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Nilai realisasi tidak dihitung selama LOP dibuka kembali. Teknisi perlu melengkapi item tambahan dan mengajukan lagi; admin menyelesaikan review agar LOP kembali Completed.</p>
        <form method="POST" action="{{ route('data-boqs.reset', $resetLop, false) }}" class="flex justify-end gap-2 pt-2">
            @csrf
            <input type="hidden" name="confirmed" value="1">
            <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-ink-200 px-4 py-2 text-sm font-bold dark:border-ink-700">Tidak, batal</button>
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Ya, reset</button>
        </form>
    </div>
</x-modal>
