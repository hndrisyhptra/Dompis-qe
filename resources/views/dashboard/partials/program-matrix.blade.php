@php
    $matrixColumns = [
        ['total', 'Total LOP', '', 'text-ink-900 dark:text-white'],
        ['assigned', 'Assign', 'assigned', 'text-blue-700 dark:text-blue-300'],
        ['unassigned', 'Unassign', 'unassigned', 'text-ink-600 dark:text-ink-300'],
        ['in_review', 'In Review', 'in_review', 'text-amber-700 dark:text-amber-300'],
        ['complete', 'Complete', 'complete', 'text-emerald-700 dark:text-emerald-300'],
        ['percentage', 'Persentase', 'complete', 'text-emerald-700 dark:text-emerald-300'],
    ];
@endphp
<div class="mt-5 overflow-hidden rounded-md border border-ink-200 dark:border-ink-700">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 bg-ink-50 px-4 py-3 dark:border-ink-700 dark:bg-ink-800">
        <p class="text-xs font-bold text-ink-700 dark:text-ink-200">{{ $scopeLabel }}</p>
        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ count($programMatrix['rows']) }} {{ $programMatrix['heading'] }} · Klik angka untuk melihat LOP</span>
    </div>
    <p class="px-4 pt-3 text-[10px] text-ink-400 sm:hidden">Geser tabel ke samping untuk melihat semua kolom.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-180 text-xs">
            <thead class="bg-ink-50 text-[10px] font-bold uppercase tracking-wide text-ink-500 dark:bg-ink-800 dark:text-ink-400">
                <tr><th scope="col" class="sticky left-0 z-10 min-w-48 border-b border-ink-200 bg-ink-50 px-4 py-3 text-left dark:border-ink-700 dark:bg-ink-800">{{ $programMatrix['heading'] }}</th>@foreach ($matrixColumns as [$key, $label])<th scope="col" class="min-w-20 border-b border-ink-200 px-3 py-3 text-center dark:border-ink-700">{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                @forelse ($programMatrix['rows'] as $row)
                    <tr class="group transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                        <th scope="row" class="sticky left-0 z-10 bg-white px-4 py-3 text-left font-bold text-ink-900 group-hover:bg-ink-50 dark:bg-ink-900 dark:text-white dark:group-hover:bg-ink-800"><p>{{ $row['label'] }}</p><p class="mt-1 max-w-56 truncate text-[10px] font-normal text-ink-400" title="{{ $row['caption'] }}">{{ $row['caption'] }}</p></th>
                        @foreach ($matrixColumns as [$key, $label, $metric, $tone])
                            <td class="px-3 py-3 text-center {{ $tone }}"><x-matrix-number :value="$row['summary'][$key]" :suffix="$key === 'percentage' ? '%' : ''" :title="$label.' — '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], $metric ? ['metric' => $metric] : [])" :class="$key === 'percentage' ? 'rounded-md bg-emerald-50 px-2 py-1 dark:bg-emerald-950/40' : ''"/></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-ink-400">Belum ada wilayah pada scope ini. Hubungi Super Admin untuk melengkapi master lokasi.</td></tr>
                @endforelse
            </tbody>
            @if ($programMatrix['rows'])
                <tfoot class="border-t border-ink-200 bg-emerald-50 font-bold dark:border-ink-700 dark:bg-emerald-950/30"><tr><th scope="row" class="sticky left-0 z-10 bg-emerald-50 px-4 py-3 text-left text-emerald-900 dark:bg-ink-900 dark:text-emerald-200">Grand Total</th>@foreach ($matrixColumns as [$key, $label, $metric, $tone])<td class="px-3 py-3 text-center {{ $tone }}"><x-matrix-number :value="$programMatrix['summary'][$key]" :suffix="$key === 'percentage' ? '%' : ''" :title="$label.' — Total Scope'" :filters="array_merge($matrixScopeFilters, $metric ? ['metric' => $metric] : [])"/></td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
</div>
