@props([
    'name',
    'label',
    'placeholder' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">
        {{ $label }}
    </label>

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition',
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        {{ $slot }}
    </select>

    @error($name)
        <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
    @enderror
</div>
