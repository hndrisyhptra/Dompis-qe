<div {{ $attributes->class(['overflow-x-auto rounded-xl border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-900']) }}>
    <table class="min-w-full divide-y divide-ink-100 dark:divide-ink-700 text-sm">
        {{ $slot }}
    </table>
</div>
