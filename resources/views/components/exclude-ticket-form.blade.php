@props(['incident', 'compact' => false])

@php $reasonId = 'exclude-reason-'.\Illuminate\Support\Str::uuid(); @endphp

<form method="POST" action="{{ route('incident-monitoring.tickets.exclude') }}"
      x-data="{ saving: false }" @submit="saving = true" @pageshow.window="saving = false" :aria-busy="saving"
      @class(['relative flex items-center', $compact ? 'gap-1' : 'gap-2'])>
    @csrf
    <input type="hidden" name="incident" value="{{ $incident }}">
    <label for="{{ $reasonId }}" class="sr-only">Alasan exclude tiket {{ $incident }}</label>
    <select id="{{ $reasonId }}" name="reason" required
            @class(['min-w-0 rounded-md border border-ink-300 bg-white text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-600 dark:bg-ink-800 dark:text-white', $compact ? 'min-h-9 w-32 px-1.5 py-1 text-xs' : 'min-h-11 w-44 px-2 py-2 text-sm'])>
        <option value="">Pilih alasan</option>
        @foreach (\App\Enums\ExcludeReason::cases() as $reason)
            <option value="{{ $reason->value }}" @selected(old('incident') === $incident && old('reason') === $reason->value)>{{ $reason->label() }}</option>
        @endforeach
    </select>
    <button type="submit" :disabled="saving" title="Exclude tiket {{ $incident }}" aria-label="Exclude tiket {{ $incident }}"
            @class(['inline-flex shrink-0 items-center justify-center rounded-md bg-brand-600 font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-60', $compact ? 'h-9 w-9' : 'min-h-11 min-w-24 px-3 text-sm'])>
        @if ($compact)
            <svg x-show="!saving" aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M8 12h8"/></svg>
            <svg x-show="saving" x-cloak aria-hidden="true" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        @else
            <span x-text="saving ? 'Menyimpan…' : 'Exclude'">Exclude</span>
        @endif
    </button>
</form>
