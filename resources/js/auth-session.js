/** Hanya form login/logout; tidak me-refresh token atau mengulang form pekerjaan. */
export function installAuthSessionGuard({
    document = globalThis.document,
    window = globalThis.window,
    fetch = globalThis.fetch,
    submitForm = form => globalThis.HTMLFormElement.prototype.submit.call(form),
} = {}) {
    const pending = new WeakSet();

    function sameOriginUrl(value) {
        const url = new URL(value, window.location.href);
        if (url.origin !== window.location.origin) throw new Error('Origin tidak sesuai');
        return url;
    }

    window.addEventListener('pageshow', event => {
        // Cache back/forward dapat mempertahankan DOM dan token session sebelumnya.
        if (event.persisted) window.location.reload();
    });

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form?.matches?.('form[data-auth-session]') || event.defaultPrevented) return;
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);

        const feedback = form.querySelector('[data-auth-session-error]');
        if (feedback) { feedback.hidden = true; feedback.textContent = ''; }
        const buttons = Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"], [data-auth-session-cancel]'));
        const states = buttons.map(button => button.disabled);
        buttons.forEach(button => { button.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        let navigating = false;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);

        try {
            const response = await fetch(sameOriginUrl(form.dataset.authSessionUrl).href, {
                method: 'GET', credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Pemeriksaan sesi gagal');
            const context = await response.json();
            if (typeof context.csrf_token !== 'string' || !context.csrf_token ||
                !Object.prototype.hasOwnProperty.call(context, 'user_id') || typeof context.redirect_url !== 'string') {
                throw new Error('Respons sesi tidak sesuai');
            }

            const mode = form.dataset.authSession;
            const changedAccount = mode === 'logout' &&
                (context.user_id === null || String(context.user_id) !== form.dataset.authUserId);
            const alreadyLoggedIn = mode === 'login' && context.user_id !== null;
            if (changedAccount || alreadyLoggedIn) {
                // Jangan logout akun lain dari halaman yang masih membawa identitas lama.
                window.location.assign(sameOriginUrl(context.redirect_url).href);
                navigating = true;
                return;
            }
            if (!['login', 'logout'].includes(mode)) throw new Error('Form tidak sesuai');
            const token = form.querySelector('input[name="_token"]');
            if (!token) throw new Error('Token form tidak tersedia');
            token.value = context.csrf_token;
            // Hanya satu POST asli setelah pemeriksaan; tidak ada retry POST otomatis.
            submitForm(form);
            navigating = true;
        } catch (_) {
            if (feedback) {
                feedback.textContent = 'Sesi belum dapat diperiksa. Periksa koneksi lalu tekan tombol sekali lagi. Isian Anda tetap tersimpan di halaman ini.';
                feedback.hidden = false;
            }
        } finally {
            window.clearTimeout(timeout);
            if (!navigating) {
                pending.delete(form);
                buttons.forEach((button, index) => { button.disabled = states[index]; });
                form.removeAttribute('aria-busy');
            }
        }
    }, true);
}
