@props([
    'name',
    'label',
    'placeholder' => null,
    'id' => null,
    'showErrors' => true,
])

@php($selectId = $id ?: $name)

<div>
    <label for="{{ $selectId }}" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">
        {{ $label }}
    </label>

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-500 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-50 dark:disabled:bg-ink-900',
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        {{ $slot }}
    </select>

    @if ($showErrors)
        @error($name)
            <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
        @enderror
    @endif
</div>
