@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'w-full rounded-lg bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white text-sm font-semibold py-2.5 shadow-sm transition',
    ]) }}
>
    {{ $slot }}
</button>
