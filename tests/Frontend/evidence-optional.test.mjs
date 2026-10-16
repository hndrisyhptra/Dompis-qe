import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf('window.evidenceUploader =');
const component = source.slice(start, source.indexOf('\n});', start) + 4);

function uploader(options = {}, fetcher = null) {
    const window = {};
    const requests = [];
    vm.runInNewContext(component, {
        window, AbortController, setTimeout, clearTimeout,
        document: { querySelector: () => ({ content: 'csrf-test' }) },
        fetch: async (url, request) => {
            requests.push({ url, ...request });
            return fetcher ? fetcher(url, request) : { ok: true, json: async () => ({ unavailable: JSON.parse(request.body).unavailable }) };
        },
        isImageFile: file => file.type.startsWith('image/'),
        URL: { createObjectURL: () => 'blob:preview' },
    });
    const card = window.evidenceUploader({ optional: true, choiceEndpoint: '/technician/projects/1/optional-capture', category: 'insera', ...options });
    return { card, requests };
}

test('Tidak Ada hanya berubah setelah server mengonfirmasi, dengan CSRF dan same-origin', async () => {
    const { card, requests } = uploader();
    card.selectionError = 'Kesalahan pilihan lama';
    const saving = card.skipOptional();
    assert.equal(card.optionalSkipped, false);
    assert.equal(card.optionalSaving, true);
    assert.equal(await saving, true);
    assert.equal(card.optionalSkipped, true);
    assert.equal(card.selectionError, '');
    assert.equal(card.queue.length, 0);
    assert.equal(card.files.length, 0);
    assert.equal(requests[0].credentials, 'same-origin');
    assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.deepEqual(JSON.parse(requests[0].body), { category: 'insera', unavailable: true });
    await card.skipOptional();
    assert.equal(requests.length, 1);
});

test('kategori wajib atau endpoint kosong tidak membuat pilihan lokal palsu', async () => {
    for (const options of [{ optional: false }, { choiceEndpoint: null }]) {
        const { card, requests } = uploader(options);
        assert.equal(await card.skipOptional(), false);
        assert.equal(card.optionalSkipped, false);
        assert.equal(requests.length, 0);
    }
});

test('Tidak Ada tidak menyembunyikan atau menghapus file pilihan, proses, gagal, sukses, atau form replacement', async () => {
    for (const status of ['selected', 'compressing', 'queued', 'uploading', 'error', 'done']) {
        const { card, requests } = uploader();
        const file = { id: 'existing-choice', status };
        card.queue.push(file);
        await card.skipOptional();
        assert.equal(card.optionalSkipped, false);
        assert.equal(card.queue[0], file);
        assert.equal(requests.length, 0);
    }
    const { card, requests } = uploader();
    card.files = [{ name: 'replacement.jpg' }];
    assert.equal(await card.skipOptional(), false);
    assert.equal(requests.length, 0);
});

test('koneksi gagal, validasi, 419, dan respons tidak lengkap tidak ditampilkan sebagai tersimpan', async () => {
    for (const fetcher of [
        async () => { throw new Error('Offline'); },
        async () => ({ ok: false, status: 422, json: async () => ({ errors: { unavailable: ['Evidence masih tersedia'] } }) }),
        async () => ({ ok: false, status: 419, json: async () => ({ message: 'Expired' }) }),
        async () => ({ ok: true, json: async () => ({}) }),
    ]) {
        const { card } = uploader({}, fetcher);
        assert.equal(await card.skipOptional(), false);
        assert.equal(card.optionalSkipped, false);
        assert.equal(card.optionalSaving, false);
        assert.ok(card.optionalError.length > 0);
    }
});

test('pilihan tersimpan dimuat ulang dan Upload jika ada membatalkannya setelah server menyimpan', async () => {
    const { card, requests } = uploader({ optionalSkipped: true, category: 'slot_port' });
    assert.equal(await card.saveOptional(false), true);
    assert.equal(card.optionalSkipped, false);
    assert.deepEqual(JSON.parse(requests[0].body), { category: 'slot_port', unavailable: false });
    const failed = uploader({ optionalSkipped: true }, async () => { throw new Error('Offline'); }).card;
    assert.equal(await failed.saveOptional(false), false);
    assert.equal(failed.optionalSkipped, true);
});

test('memilih foto setelah Tidak Ada mempertahankan preview dan membatalkan pilihan tersimpan', async () => {
    const { card } = uploader({ optionalSkipped: true });
    await card.selectQueue({ target: { files: [], value: '' } });
    assert.equal(card.optionalSkipped, true);
    const raw = { name: 'capture.jpg', size: 123, type: 'image/jpeg' };
    const input = { files: [raw], value: 'capture.jpg' };
    await card.selectQueue({ target: input });
    assert.equal(card.optionalSkipped, false);
    assert.equal(card.queue.length, 1);
    assert.equal(card.queue[0].status, 'selected');
    assert.equal(card.queue[0].previewUrl, 'blob:preview');
    assert.equal(card.queue[0].raw, raw);
    assert.equal(input.value, '');
});

test('klik ganda diblokir selama penyimpanan berlangsung', async () => {
    let resolve;
    const { card, requests } = uploader({}, () => new Promise(done => { resolve = done; }));
    const first = card.skipOptional();
    assert.equal(await card.skipOptional(), false);
    assert.equal(requests.length, 1);
    resolve({ ok: true, json: async () => ({ unavailable: true }) });
    assert.equal(await first, true);
    assert.equal(card.optionalSaving, false);
});
