@props(['id', 'title' => null, 'size' => null])

<section id="{{ $id }}" aria-label="{{ $title }}" {{ $attributes->class('overflow-hidden rounded-md border border-ink-200 bg-white p-5 dark:border-ink-700 dark:bg-ink-900') }}>
    {{ $slot }}
</section>
