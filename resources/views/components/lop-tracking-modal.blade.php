@props(['id', 'lop'])

<x-modal :id="$id" title="Tracking LOP" size="lg">
    <div x-data="{ tab: 'activity' }" class="space-y-5">
        <div class="rounded-xl bg-ink-50 p-4 dark:bg-ink-800">
            <p class="text-xs font-extrabold uppercase tracking-wide text-brand-600 dark:text-brand-300">{{ $lop->incident }}</p>
            <p class="mt-1 truncate text-sm font-bold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</p>
        </div>

        <div class="grid grid-cols-2 rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
            <button type="button" @click="tab = 'activity'" :class="tab === 'activity' ? 'bg-white text-ink-900 shadow-sm dark:bg-ink-700 dark:text-white' : 'text-ink-500'" class="rounded-lg px-3 py-2 text-xs font-bold transition">Aktivitas LOP</button>
            <button type="button" @click="tab = 'assignment'" :class="tab === 'assignment' ? 'bg-white text-ink-900 shadow-sm dark:bg-ink-700 dark:text-white' : 'text-ink-500'" class="rounded-lg px-3 py-2 text-xs font-bold transition">Riwayat Assignment</button>
        </div>

        <div x-show="tab === 'activity'" class="space-y-0">
            @forelse ($lop->histories->sortByDesc('created_at') as $history)
                @php
                    $before = \App\Enums\LopStatus::tryFrom($history->status_before ?? '')?->label();
                    $after = \App\Enums\LopStatus::tryFrom($history->status_after ?? '')?->label() ?? $history->status_after;
                @endphp
                <div class="relative flex gap-3 pb-5 last:pb-0">
                    @if (! $loop->last)<span class="absolute bottom-0 left-[15px] top-8 w-px bg-ink-200 dark:bg-ink-700"></span>@endif
                    <span class="relative z-10 mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600 ring-4 ring-white dark:bg-brand-950/40 dark:ring-ink-900">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    </span>
                    <div class="min-w-0 flex-1 rounded-xl border border-ink-100 p-3 dark:border-ink-700">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-bold text-ink-900 dark:text-white">{{ $history->event_type === 'created' ? 'LOP dibuat' : ($before ? $before.' → '.$after : $after) }}</p>
                            <time class="shrink-0 text-[10px] text-ink-400">{{ $history->created_at->format('d M, H:i') }}</time>
                        </div>
                        @if ($history->note)<p class="mt-1 text-xs leading-5 text-ink-500 dark:text-ink-400">{{ $history->note }}</p>@endif
                        <p class="mt-2 text-[10px] font-semibold text-ink-400">oleh {{ $history->user?->name ?? 'Sistem' }}</p>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-ink-200 p-8 text-center text-sm text-ink-400 dark:border-ink-700">Belum ada aktivitas.</p>
            @endforelse
        </div>

        <div x-show="tab === 'assignment'" class="space-y-3">
            @forelse ($lop->assignments->sortByDesc('assigned_at') as $assignment)
                <div class="rounded-xl border border-ink-100 p-4 dark:border-ink-700">
                    <div class="flex items-start gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $assignment->status->value === 'active' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30' : 'bg-ink-100 text-ink-500 dark:bg-ink-800' }} text-sm font-extrabold">{{ mb_strtoupper(mb_substr($assignment->technician?->name ?? '?', 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-ink-900 dark:text-white">{{ $assignment->technician?->name ?? 'Teknisi tidak tersedia' }}</p>
                                <x-badge :variant="$assignment->status->value === 'active' ? 'success' : 'neutral'">{{ $assignment->status->value === 'active' ? 'Aktif' : 'Selesai' }}</x-badge>
                            </div>
                            <p class="mt-1 text-xs text-ink-500">Ditugaskan oleh {{ $assignment->assigner?->name ?? '—' }}</p>
                            <div class="mt-3 flex flex-wrap gap-3 text-[10px] text-ink-400">
                                <span>Mulai: {{ $assignment->assigned_at->format('d M Y, H:i') }}</span>
                                @if ($assignment->unassigned_at)<span>Selesai: {{ $assignment->unassigned_at->format('d M Y, H:i') }}</span>@endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-ink-200 p-8 text-center text-sm text-ink-400 dark:border-ink-700">LOP belum pernah di-assign.</p>
            @endforelse
        </div>
    </div>
</x-modal>
