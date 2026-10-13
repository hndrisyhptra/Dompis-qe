@props([
    'id' => 'logout-confirmation',
    'buttonClass' => '',
])

<div x-data="{}" {{ $attributes }}>
    <button type="button" data-logout-trigger title="Logout dari aplikasi" aria-haspopup="dialog" aria-controls="{{ $id }}" @click="$refs.confirmLogout.showModal()" class="{{ $buttonClass }}">{{ $slot }}</button>
    <dialog id="{{ $id }}" x-ref="confirmLogout" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description"
            @cancel="if ($refs.logoutForm.getAttribute('aria-busy') === 'true') $event.preventDefault()"
            @click.self="if ($refs.logoutForm.getAttribute('aria-busy') !== 'true') $el.close()"
            class="fixed inset-0 m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-sm overflow-y-auto rounded-md border border-ink-200 bg-white p-0 text-ink-900 shadow-2xl backdrop:bg-ink-950/50">
        <form x-ref="logoutForm" method="POST" action="{{ route('logout', [], false) }}" data-auth-session="logout" data-auth-user-id="{{ auth()->id() }}" data-auth-session-url="{{ route('auth.session', [], false) }}" class="group space-y-5 p-6">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-md border border-ink-200 bg-ink-50 text-ink-600"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12"/></svg></span>
                <button type="button" data-auth-session-cancel @click="$refs.confirmLogout.close()" aria-label="Tutup konfirmasi logout" title="Tutup" class="grid h-8 w-8 place-items-center rounded-md text-lg text-ink-400 hover:bg-ink-50 hover:text-ink-700 disabled:cursor-wait disabled:opacity-40">×</button>
            </div>
            <div><h2 id="{{ $id }}-title" class="text-lg font-extrabold text-ink-900">Keluar dari akun?</h2><p id="{{ $id }}-description" class="mt-2 text-sm leading-6 text-ink-500">Anda akan keluar dari sesi ini. Pastikan perubahan pekerjaan sudah disimpan sebelum melanjutkan.</p><p class="mt-3 break-words text-xs font-semibold text-ink-700">{{ auth()->user()?->name }} · {{ auth()->user()?->role?->name }}</p></div>
            <p data-auth-session-error hidden role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-700"></p>
            <p class="hidden text-xs text-ink-500 group-aria-busy:block" role="status">Memeriksa sesi, mohon tunggu…</p>
            <div class="flex gap-2 border-t border-ink-100 pt-4">
                <button type="button" data-auth-session-cancel autofocus @click="$refs.confirmLogout.close()" class="min-h-10 flex-1 rounded-md border border-ink-200 bg-white px-4 text-xs font-bold text-ink-700 transition hover:bg-ink-50 focus:outline-none focus:ring-2 focus:ring-ink-300 disabled:cursor-wait disabled:opacity-40">Batal</button>
                <button type="submit" data-logout-confirm class="min-h-10 flex-1 rounded-md bg-ink-900 px-4 text-xs font-bold text-white transition hover:bg-ink-800 focus:outline-none focus:ring-2 focus:ring-ink-300 disabled:cursor-wait disabled:opacity-50">Ya, Logout</button>
            </div>
        </form>
    </dialog>
</div>
