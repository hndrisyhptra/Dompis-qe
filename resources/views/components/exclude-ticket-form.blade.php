@props(['incident'])

@php $reasonId = 'exclude-reason-'.\Illuminate\Support\Str::uuid(); @endphp

<form method="POST" action="{{ route('incident-monitoring.tickets.exclude') }}"
      x-data="{ saving: false }" @submit="saving = true" @pageshow.window="saving = false" :aria-busy="saving"
      class="relative flex items-center gap-2">
    @csrf
    <input type="hidden" name="incident" value="{{ $incident }}">
    <label for="{{ $reasonId }}" class="sr-only">Alasan exclude tiket {{ $incident }}</label>
    <select id="{{ $reasonId }}" name="reason" required
            class="min-h-11 min-w-0 w-44 rounded-md border border-ink-300 bg-white px-2 py-2 text-sm text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-600 dark:bg-ink-800 dark:text-white">
        <option value="">Pilih alasan</option>
        @foreach (\App\Enums\ExcludeReason::cases() as $reason)
            <option value="{{ $reason->value }}" @selected(old('incident') === $incident && old('reason') === $reason->value)>{{ $reason->label() }}</option>
        @endforeach
    </select>
    <button type="submit" :disabled="saving" title="Exclude tiket {{ $incident }}"
            class="min-h-11 min-w-24 shrink-0 rounded-md bg-brand-600 px-3 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-60">
        <span x-text="saving ? 'Menyimpan…' : 'Exclude'">Exclude</span>
    </button>
</form>
