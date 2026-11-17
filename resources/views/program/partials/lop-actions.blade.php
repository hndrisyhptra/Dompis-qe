<div x-data="{ actionsOpen: false, actionsPosition: { top: 8, left: 8 }, toggleActions(button, menuId) { if (this.actionsOpen) { this.actionsOpen = false; return; } this.actionsOpen = true; this.$nextTick(() => { const anchor = button.getBoundingClientRect(); const menu = document.getElementById(menuId); if (!menu) return; const size = menu.getBoundingClientRect(); const gap = 6; const below = anchor.bottom + size.height + gap <= window.innerHeight - 8; this.actionsPosition = { top: below ? anchor.bottom + gap : Math.max(8, anchor.top - size.height - gap), left: Math.max(8, Math.min(anchor.right - size.width, window.innerWidth - size.width - 8)) }; }); } }"
     @click.window="if (actionsOpen && !$event.target.closest('#program-lop-toggle-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}, #program-lop-actions-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}')) actionsOpen = false"
     @keydown.escape.window="actionsOpen = false" @scroll.window="actionsOpen = false" @resize.window="actionsOpen = false"
     class="flex items-center justify-center gap-1.5">
    <div class="flex items-center gap-1.5">
        @can('assign', $lop)
            <x-table-action :label="$lop->activeAssignment ? 'Reassign Teknisi untuk '.$lop->incident : 'Assign Teknisi untuk '.$lop->incident" tone="primary" :data-lazy-modal-url="route('program.modal', ['program' => $programType->value, 'qe_lop' => $lop, 'kind' => 'assign', ...$modalCarry], false)" :data-lazy-modal-target="'program-assign-'.$lop->id_qe_lops">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-6.75-3.75A3.75 3.75 0 1 1 6.75 6.75a3.75 3.75 0 0 1 7.5 0ZM3 20.25a6.75 6.75 0 0 1 13.5 0v.75H3v-.75Z"/></svg>
            </x-table-action>
        @endcan
        <x-table-action label="Detail LOP {{ $lop->incident }}" :data-lazy-modal-url="route('program.modal', ['program' => $programType->value, 'qe_lop' => $lop, 'kind' => 'detail', ...$modalCarry], false)" :data-lazy-modal-target="'program-detail-'.$lop->id_qe_lops">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg>
        </x-table-action>
        <x-table-action label="Aksi lainnya untuk LOP {{ $lop->incident }}" id="program-lop-toggle-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}" data-program-action-toggle aria-controls="program-lop-actions-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}" @click="toggleActions($el, 'program-lop-actions-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}')" ::aria-expanded="actionsOpen">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
        </x-table-action>
    </div>

    <template x-teleport="body">
        <div id="program-lop-actions-{{ $lop->id_qe_lops }}-{{ $presentation ?? 'row' }}" x-show="actionsOpen" x-cloak data-program-action-menu
             @click="if ($event.target.closest('button, a')) actionsOpen = false"
             :style="`top:${actionsPosition.top}px;left:${actionsPosition.left}px`"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-1 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-1 scale-95 opacity-0"
             class="fixed z-[120] max-h-[calc(100vh-1rem)] w-60 origin-top-right overflow-y-auto rounded-md border border-ink-200 bg-white p-1 shadow-xl dark:border-ink-700 dark:bg-ink-900">
            <div class="flex flex-wrap items-center gap-1.5">
                <x-table-action label="Tracking Riwayat {{ $lop->incident }}" tone="info" :data-lazy-modal-url="route('program.modal', ['program' => $programType->value, 'qe_lop' => $lop, 'kind' => 'tracking', ...$modalCarry], false)" :data-lazy-modal-target="'program-tracking-'.$lop->id_qe_lops">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </x-table-action>
                @if ($baDocument)
                    <x-table-action label="Download BA Bongkar {{ $lop->incident }}" tone="info" :href="route('lop.ba-bongkar.download', $lop)">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25h-4.5A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-6.375Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25v6m-3-3 3 3 3-3"/></svg>
                    </x-table-action>
                @endif
                @if ($lop->status_lop === \App\Enums\LopStatus::COMPLETED)
                    <x-table-action label="Download evidence {{ $lop->incident }}" tone="success" :href="route('lop.evidence-archive', $lop)">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    </x-table-action>
                @endif
                @if ($lop->program_type->usesProjectStatus())
                    @can('uploadEvidence', $lop)
                        <x-table-action label="Kelola Surat Permintaan {{ $lop->incident }}" tone="primary" :data-lazy-modal-url="route('program.modal', ['program' => $programType->value, 'qe_lop' => $lop, 'kind' => 'request-letter', ...$modalCarry], false)" :data-lazy-modal-target="'program-request-letter-'.$lop->id_qe_lops">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25h-4.5A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-6.375Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75h6.75m-3.375-3.375v6.75"/></svg>
                        </x-table-action>
                    @endcan
                @endif
            </div>
        </div>
    </template>
</div>
