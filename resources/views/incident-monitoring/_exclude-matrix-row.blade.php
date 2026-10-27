@if ($showRowLabel ?? true)
    <th scope="row" class="sticky left-0 z-10 border border-ink-200 bg-inherit py-2 text-left dark:border-ink-700 {{ $groupByBranch && $matrixRow['key'] !== '' ? 'pl-10 pr-3' : 'px-3' }}">{{ $matrixRow['label'] }}</th>
@endif
@foreach ($kinds as $kind => $label)
    @foreach (['excluded' => 'Sudah exclude', 'not_excluded' => 'Belum exclude'] as $state => $stateLabel)
        @include('incident-monitoring._exclude-count', ['cellKey' => $kind.':'.$state, 'cellLabel' => $label.' · '.$stateLabel, 'cellQuery' => ['matrix_region' => $matrixRow['key'], 'detail_kind' => $kind, 'exclude_state' => $state]])
    @endforeach
@endforeach
@foreach ($kinds as $kind => $label)
    @foreach ($reasons as $reason => $reasonLabel)
        @include('incident-monitoring._exclude-count', ['cellKey' => $kind.':'.$reason, 'cellLabel' => $label.' · '.$reasonLabel, 'cellQuery' => ['matrix_region' => $matrixRow['key'], 'detail_kind' => $kind, 'exclude_state' => 'excluded', 'reason' => $reason]])
    @endforeach
@endforeach
