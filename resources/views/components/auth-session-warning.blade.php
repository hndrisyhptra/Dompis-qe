@if (session('auth_warning'))
    <div role="alert" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        {{ session('auth_warning') }}
    </div>
@endif
