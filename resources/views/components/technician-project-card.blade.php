@props(['project'])

@php
    $summary = $project->progress_summary ?? app(\App\Services\ProjectProgressService::class)->summary($project);
    $attention = $summary['review_key'] === 'rejected';
    $progress = $summary['percentage'];
@endphp

<a href="{{ route('technician.projects.show', $project) }}"
   class="block rounded-2xl border {{ $attention ? 'border-brand-200 bg-brand-50/70 dark:border-brand-900 dark:bg-brand-900/10' : 'border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900' }} p-4 shadow-sm transition active:scale-[.99]">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-400">{{ $project->incident }}</span>
                @if ($attention)<span class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">Perlu aksi</span>@endif
            </div>
            <h3 class="mt-1 line-clamp-2 text-sm font-bold leading-5 text-ink-900 dark:text-white">{{ $project->nama_lop }}</h3>
        </div>
        <x-badge :variant="$summary['review_variant']" class="shrink-0 whitespace-nowrap">{{ $summary['review_label'] }}</x-badge>
    </div>
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
        <span>{{ $project->sto ?: 'STO belum diisi' }}</span>
        <span>{{ $project->branch ?: 'Branch belum diisi' }}</span>
        <span>{{ $project->program_type->label() }}</span>
    </div>
    <div class="mt-4">
        <div class="mb-1.5 flex justify-between text-[11px] font-medium text-ink-500 dark:text-ink-400">
            <span>{{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step lengkap</span><span>{{ $progress }}%</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
            <div class="h-full rounded-full {{ $attention ? 'bg-brand-500' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-brand-600')) }} transition-all duration-500" style="width: {{ $progress }}%"></div>
        </div>
        <div class="mt-2 flex items-center justify-between text-[10px] text-ink-400">
            <span>{{ $summary['evidence_count'] }} evidence</span>
            @if ($summary['rejected_count'])<span class="font-bold text-brand-600">{{ $summary['rejected_count'] }} perlu diperbaiki</span>
            @elseif ($summary['pending_count'])<span>{{ $summary['pending_count'] }} menunggu review</span>
            @elseif ($summary['approved_count'])<span class="font-bold text-emerald-600">Semua disetujui</span>@endif
        </div>
    </div>
</a>
