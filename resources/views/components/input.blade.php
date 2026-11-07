@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'id' => null,
    'useOld' => true,
    'showErrors' => true,
])

@php($inputId = $id ?: $name)

<div>
    <label for="{{ $inputId }}" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">
        {{ $label }}
    </label>

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $useOld ? old($name, $value) : $value }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->class([
            'min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition duration-150 placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-500 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-50 dark:placeholder:text-ink-500 dark:disabled:bg-ink-900',
        ]) }}
    >

    @if ($showErrors)
        @error($name)
            <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
        @enderror
    @endif
</div>
