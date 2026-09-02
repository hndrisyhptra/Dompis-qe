@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">
        {{ $label }}
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->class([
            'w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition',
        ]) }}
    >

    @error($name)
        <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
    @enderror
</div>
