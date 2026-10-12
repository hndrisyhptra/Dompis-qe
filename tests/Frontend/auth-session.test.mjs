import assert from 'node:assert/strict';
import test from 'node:test';
import { installAuthSessionGuard } from '../../resources/js/auth-session.js';

function setup({ mode = 'login', userId = '', context = { csrf_token: 'fresh-token', user_id: null, redirect_url: '/login' }, request } = {}) {
    const listeners = {};
    const pageEvents = {};
    const requests = [];
    const submits = [];
    const redirects = [];
    let reloads = 0;
    const document = { addEventListener(name, listener) { listeners[name] = listener; } };
    const window = {
        addEventListener(name, listener) { pageEvents[name] = listener; },
        setTimeout() { return 1; }, clearTimeout() {},
        location: { href: 'https://dompis.test/dompis-qe/login', origin: 'https://dompis.test',
            assign(url) { redirects.push(url); }, reload() { reloads++; } },
    };
    const token = { value: 'old-token' };
    const feedback = { hidden: true, textContent: '' };
    const button = { disabled: false };
    const form = {
        dataset: { authSession: mode, authUserId: userId, authSessionUrl: '/dompis-qe/auth/session' },
        attributes: {}, password: 'kept-only-in-form',
        matches: selector => selector === 'form[data-auth-session]',
        querySelector: selector => selector === 'input[name="_token"]' ? token : feedback,
        querySelectorAll: () => [button],
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    };
    installAuthSessionGuard({ document, window,
        fetch: async (url, options) => { requests.push({ url, options }); return request ? request() : { ok: true, json: async () => context }; },
        submitForm: value => submits.push({ form: value, token: token.value }),
    });
    const submit = (target = form) => {
        const event = { target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        return listeners.submit(event);
    };
    return { form, token, feedback, button, requests, submits, redirects, pageEvents, submit, get reloads() { return reloads; } };
}

test('login memperbarui hanya token form, lalu POST satu kali dengan cookie same-origin', async () => {
    const app = setup();
    await app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].url, 'https://dompis.test/dompis-qe/auth/session');
    assert.equal(app.requests[0].options.credentials, 'same-origin');
    assert.equal(app.requests[0].options.cache, 'no-store');
    assert.equal(app.submits.length, 1);
    assert.equal(app.submits[0].token, 'fresh-token');
    assert.equal(app.form.password, 'kept-only-in-form');
});

test('logout memakai token segar hanya jika identitas akun masih sesuai', async () => {
    const app = setup({ mode: 'logout', userId: '7', context: { csrf_token: 'fresh', user_id: 7, redirect_url: '/dashboard' } });
    await app.submit();
    assert.equal(app.submits.length, 1);
    assert.equal(app.redirects.length, 0);
});

test('logout dari tab akun lama tidak boleh logout akun lain', async () => {
    const app = setup({ mode: 'logout', userId: '7', context: { csrf_token: 'fresh', user_id: 8, redirect_url: '/technician' } });
    await app.submit();
    assert.equal(app.submits.length, 0);
    assert.equal(app.token.value, 'old-token');
    assert.deepEqual(app.redirects, ['https://dompis.test/technician']);
});

test('logout session expired menuju login tanpa POST tambahan', async () => {
    const app = setup({ mode: 'logout', userId: '7' });
    await app.submit();
    assert.equal(app.submits.length, 0);
    assert.deepEqual(app.redirects, ['https://dompis.test/login']);
});

test('halaman login lama tidak mengganti akun yang telah aktif', async () => {
    const app = setup({ context: { csrf_token: 'fresh', user_id: 8, redirect_url: '/dashboard' } });
    await app.submit();
    assert.equal(app.submits.length, 0);
    assert.deepEqual(app.redirects, ['https://dompis.test/dashboard']);
});

test('koneksi gagal menjaga isian dan mengaktifkan tombol agar bisa mencoba lagi', async () => {
    let fail = true;
    const app = setup({ request: () => {
        if (fail) throw new Error('offline');
        return { ok: true, json: async () => ({ csrf_token: 'fresh', user_id: null, redirect_url: '/login' }) };
    } });
    await app.submit();
    assert.equal(app.submits.length, 0);
    assert.equal(app.feedback.hidden, false);
    assert.match(app.feedback.textContent, /Periksa koneksi/);
    assert.equal(app.button.disabled, false);
    assert.equal(app.form.password, 'kept-only-in-form');
    fail = false;
    await app.submit();
    assert.equal(app.submits.length, 1);
});

test('form pekerjaan tidak diperiksa, tidak diubah tokennya, dan tidak dikirim ulang', async () => {
    const app = setup();
    const operational = { matches: () => false };
    await app.submit(operational);
    assert.equal(app.requests.length, 0);
    assert.equal(app.token.value, 'old-token');
    assert.equal(app.submits.length, 0);
});

test('hanya pageshow dari cache back/forward yang memuat ulang halaman', () => {
    const app = setup();
    app.pageEvents.pageshow({ persisted: false });
    assert.equal(app.reloads, 0);
    app.pageEvents.pageshow({ persisted: true });
    assert.equal(app.reloads, 1);
});

test('endpoint atau redirect beda origin dan respons tidak lengkap ditolak tanpa POST', async () => {
    const external = setup();
    external.form.dataset.authSessionUrl = 'https://evil.test/auth/session';
    await external.submit();
    assert.equal(external.requests.length, 0);
    assert.equal(external.submits.length, 0);
    const redirect = setup({ context: { csrf_token: 'fresh', user_id: 8, redirect_url: 'https://evil.test/' } });
    await redirect.submit();
    assert.equal(redirect.redirects.length, 0);
    assert.equal(redirect.submits.length, 0);
    const incomplete = setup({ context: { csrf_token: 'fresh', redirect_url: '/login' } });
    await incomplete.submit();
    assert.equal(incomplete.submits.length, 0);
    assert.equal(incomplete.feedback.hidden, false);
});

test('klik ganda saat pemeriksaan masih berjalan hanya menimbulkan satu request', async () => {
    let release;
    const wait = new Promise(resolve => { release = resolve; });
    const app = setup({ request: () => wait });
    const first = app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    release({ ok: true, json: async () => ({ csrf_token: 'fresh', user_id: null, redirect_url: '/login' }) });
    await first;
    assert.equal(app.submits.length, 1);
});
