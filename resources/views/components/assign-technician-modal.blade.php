@props([
    'id',
    'lop',
    'technicians',
    'returnTo' => 'show',
])

<x-modal :id="$id" title="Assign Teknisi" size="lg">
    <form method="POST" action="{{ route('lop.assign', $lop) }}"
          x-data="{ query: '', selected: '', selectedName: '' }"
          class="space-y-5">
        @csrf
        <input type="hidden" name="technician_id" :value="selected">
        <input type="hidden" name="return_to" value="{{ $returnTo }}">

        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-ink-900 to-ink-800 p-4 text-white dark:from-ink-800 dark:to-ink-900">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[.18em] text-brand-300">Project yang ditugaskan</p>
                    <p class="mt-2 text-xs font-extrabold uppercase tracking-wide text-brand-300">{{ $lop->incident }}</p>
                    <h4 class="mt-1 truncate text-base font-bold">{{ $lop->nama_lop }}</h4>
                    <p class="mt-2 text-xs text-ink-300">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->wbs_type->label() }}</p>
                </div>
                <span class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold">{{ $lop->status_lop->label() }}</span>
            </div>
        </div>

        @if ($lop->activeAssignment?->technician)
            <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-3 dark:border-blue-900/50 dark:bg-blue-950/30">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($lop->activeAssignment->technician->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-300">Teknisi saat ini</p>
                    <p class="mt-0.5 truncate text-sm font-bold text-ink-900 dark:text-white">{{ $lop->activeAssignment->technician->name }}</p>
                </div>
                <x-badge variant="info">Aktif</x-badge>
            </div>
        @endif

        <div>
            <label :for="'technician-search-{{ $lop->id_qe_lops }}'" class="mb-1.5 block text-sm font-semibold text-ink-800 dark:text-ink-200">Pilih teknisi aktif</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                <input id="technician-search-{{ $lop->id_qe_lops }}" type="search" x-model="query" placeholder="Cari nama, username, NIK, atau branch..."
                       class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 pl-10 pr-4 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
            </div>
        </div>

        <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
            @forelse ($technicians as $technician)
                @php
                    $searchable = mb_strtolower(implode(' ', array_filter([
                        $technician->name, $technician->username, $technician->nik,
                        $technician->branch?->name, $technician->branch?->region,
                    ])));
                    $isCurrent = $lop->activeAssignment?->technician_id === $technician->id_user;
                @endphp
                <button type="button"
                        @disabled($isCurrent)
                        x-show="@js($searchable).includes(query.toLowerCase())"
                        @click="selected = @js((string) $technician->id_user); selectedName = @js($technician->name)"
                        :class="selected === @js((string) $technician->id_user) ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/15 dark:bg-brand-950/30' : 'border-ink-100 bg-white hover:border-ink-300 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:hover:bg-ink-800'"
                        class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-60">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-ink-100 text-sm font-extrabold text-ink-600 dark:bg-ink-700 dark:text-ink-200">{{ mb_strtoupper(mb_substr($technician->name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-bold text-ink-900 dark:text-white">{{ $technician->name }}</span>
                            @if ($isCurrent)<span class="rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-bold text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">Saat ini</span>@endif
                        </span>
                        <span class="mt-1 block truncate text-xs text-ink-500 dark:text-ink-400">{{ $technician->username }}@if($technician->nik) · NIK {{ $technician->nik }}@endif</span>
                        <span class="mt-1 block truncate text-[10px] font-semibold uppercase tracking-wide text-ink-400">{{ $technician->branch?->name ?? 'Branch belum ditentukan' }}</span>
                    </span>
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border-2"
                          :class="selected === @js((string) $technician->id_user) ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-200 text-transparent dark:border-ink-600'">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    </span>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-ink-200 p-8 text-center dark:border-ink-700">
                    <p class="text-sm font-semibold text-ink-600 dark:text-ink-300">Belum ada teknisi aktif</p>
                    <p class="mt-1 text-xs text-ink-400">Aktifkan atau tambahkan akun teknisi terlebih dahulu.</p>
                </div>
            @endforelse
        </div>

        <div x-show="selected" x-transition class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
            <span class="font-medium">Teknisi dipilih:</span> <strong x-text="selectedName"></strong>
        </div>

        @error('technician_id')
            <p class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</p>
        @enderror

        <div class="flex flex-col-reverse gap-2 border-t border-ink-100 pt-4 dark:border-ink-800 sm:flex-row sm:justify-end">
            @can('unassign', $lop)
                <button type="submit" form="unassign-{{ $lop->id_qe_lops }}" class="min-h-11 rounded-xl px-4 text-sm font-bold text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30 sm:mr-auto">
                    Hapus Assignment
                </button>
            @endcan
            <button type="button" onclick="this.closest('dialog').close()" class="min-h-11 rounded-xl px-5 text-sm font-semibold text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">Batal</button>
            <button type="submit" :disabled="!selected"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-bold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-40">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-6.75-3.75A3.75 3.75 0 1 1 6.75 6.75a3.75 3.75 0 0 1 7.5 0ZM3 20.25a6.75 6.75 0 0 1 13.5 0v.75H3v-.75Z" /></svg>
                Konfirmasi Assign
            </button>
        </div>
    </form>
    @can('unassign', $lop)
        <form id="unassign-{{ $lop->id_qe_lops }}" method="POST" action="{{ route('lop.unassign', $lop) }}" onsubmit="return confirm('Hapus assignment aktif dan kembalikan LOP ke Draft?')">
            @csrf
            @method('DELETE')
        </form>
    @endcan
</x-modal>
