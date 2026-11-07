<div {{ $attributes->class(['overflow-x-auto overscroll-x-contain rounded-xl border border-ink-100 bg-white [-webkit-overflow-scrolling:touch] dark:border-ink-700 dark:bg-ink-900']) }}>
    <table class="min-w-full divide-y divide-ink-100 dark:divide-ink-700 text-sm">
        {{ $slot }}
    </table>
</div>
