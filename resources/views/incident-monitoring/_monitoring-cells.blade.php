@php
    $m = $matrixRow['monitoring'];
    $base = 'border border-ink-200 px-2 py-2.5 text-center tabular-nums dark:border-ink-700 '.($sourceUnavailable ? 'text-ink-400 dark:text-ink-500' : 'text-ink-900 dark:text-white');
    $rowLabel = $matrixRow['label'] ?? '';
    $isGroupRow = ! ($showLabel ?? true) && ($matrixRow['key'] ?? '') === '';
    $detailUrl = function (string $cell, string $kind) use ($filters, $matrixRow, $isGroupRow): string {
        return route('incident-monitoring.exclusions.detail', array_filter([
            ...$filters,
            'matrix_region' => $matrixRow['key'] ?? '',
            'matrix_group' => $isGroupRow ? ($matrixRow['group'] ?? '') : '',
            'detail_kind' => $kind === 'total' ? '' : $kind,
            'cell' => $cell,
        ], fn ($value) => $value !== '' && $value !== null));
    };
    $cellTitle = function (string $kind, string $title) use ($monitoringKinds, $rowLabel): string {
        return trim(($monitoringKinds[$kind] ?? 'Total').' · '.$title.($rowLabel !== '' ? ' · '.$rowLabel : ''));
    };
@endphp
@if ($showLabel ?? true)
    <th scope="row" class="sticky left-0 z-10 border border-ink-200 bg-inherit py-2 text-left dark:border-ink-700 {{ $isGrouped ? ($groupByBranch ? 'pl-10 pr-3' : 'pl-8 pr-3') : 'px-3' }}">{{ $matrixRow['label'] }}</th>
@endif
@if ($monitoringMode === 'ticket')
    @foreach ($monitoringKinds as $kind => $label)
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['ticket_qe'], 'url' => $detailUrl('ticket_qe', $kind),
            'title' => $cellTitle($kind, 'Tiket QE'), 'class' => $base.' bg-ink-50/60 dark:bg-ink-800/40',
        ])
    @endforeach
    @foreach ($monitoringKinds as $kind => $label)
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['sudah_mapping'], 'url' => $detailUrl('sudah_mapping', $kind),
            'title' => $cellTitle($kind, 'Sudah mapping'), 'class' => $base.' bg-emerald-50/70 dark:bg-emerald-950/20',
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['belum_mapping'], 'url' => $detailUrl('belum_mapping', $kind),
            'title' => $cellTitle($kind, 'Belum mapping'), 'class' => $base.' bg-brand-100/70 dark:bg-brand-900/30',
        ])
    @endforeach
@elseif ($monitoringMode === 'pareto')
    @foreach ($monitoringKinds as $kind => $label)
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['create_lop'], 'url' => $detailUrl('create_lop', $kind),
            'title' => $cellTitle($kind, 'Create LOP'), 'class' => $base.' bg-blue-50/70 dark:bg-blue-950/20',
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['temporer'], 'url' => $detailUrl('temporer', $kind),
            'title' => $cellTitle($kind, 'Temporer Gamas'), 'class' => $base,
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['non_material'], 'url' => $detailUrl('non_material', $kind),
            'title' => $cellTitle($kind, 'Tidak ada material'), 'class' => $base,
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['dobel_tiket'], 'url' => $detailUrl('duplikat_open', $kind),
            'title' => $cellTitle($kind, 'Double tiket'), 'class' => $base,
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['magu_pasif'], 'url' => $detailUrl('magu_ganti_pasif_odp', $kind),
            'title' => $cellTitle($kind, 'MAGU pasif'), 'class' => $base,
        ])
    @endforeach
@else
    @foreach ($monitoringKinds as $kind => $label)
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['insera'], 'url' => $detailUrl('insera', $kind),
            'title' => $cellTitle($kind, 'Tiket Insera'), 'class' => $base.' bg-blue-50/70 dark:bg-blue-950/20',
        ])
        @include('incident-monitoring._monitoring-number', [
            'value' => $m[$kind]['manual'], 'url' => $detailUrl('manual', $kind),
            'title' => $cellTitle($kind, 'Tiket manual'), 'class' => $base.' bg-amber-50/70 dark:bg-amber-950/20',
            'disabled' => $kind !== 'total',
        ])
    @endforeach
@endif
