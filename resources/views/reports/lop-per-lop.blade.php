@extends('layouts.app')

@section('title', $title.' — '.$lop->incident)

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <a href="{{ route('lop.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900 dark:text-ink-400">&larr; Kembali ke Inbox</a>
        <h1 class="mt-2 text-xl font-extrabold text-ink-900 dark:text-white">{{ $title }} — {{ $lop->incident }}</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400">{{ $lop->nama_lop }} · {{ $lop->branch }} · {{ $lop->program_type->label() }}</p>
    </div>

    @include('reports._summary', ['grand' => $data['grand'], 'report' => $report, 'mode' => 'per_lop', 'priced' => $priced])

    @include('reports._per-lop', ['data' => $data, 'report' => $report, 'priced' => $priced, 'package' => $package])
</div>
@endsection
