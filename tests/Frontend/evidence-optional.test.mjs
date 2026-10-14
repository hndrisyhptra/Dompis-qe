import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf('window.evidenceUploader =');
const component = source.slice(start, source.indexOf('\n});', start) + 4);

function uploader(optional = true) {
    const window = {};
    vm.runInNewContext(component, {
        window,
        isImageFile: file => file.type.startsWith('image/'),
        URL: { createObjectURL: () => 'blob:preview' },
    });
    return window.evidenceUploader({ optional });
}

test('Tidak Ada hanya tersedia untuk upload opsional, tanpa request atau file baru', () => {
    const card = uploader();
    card.selectionError = 'Kesalahan pilihan lama';
    card.skipOptional();
    assert.equal(card.optionalSkipped, true);
    assert.equal(card.selectionError, '');
    assert.equal(card.queue.length, 0);
    assert.equal(card.files.length, 0);
    const mandatory = uploader(false);
    mandatory.skipOptional();
    assert.equal(mandatory.optionalSkipped, false);
});

test('Tidak Ada tidak menyembunyikan atau menghapus file pilihan, proses, gagal, atau sukses', () => {
    for (const status of ['selected', 'compressing', 'queued', 'uploading', 'error', 'done']) {
        const card = uploader();
        const file = { id: 'existing-choice', status };
        card.queue.push(file);
        card.skipOptional();
        assert.equal(card.optionalSkipped, false);
        assert.equal(card.queue[0], file);
    }
});

test('memilih foto setelah Tidak Ada membuka uploader kembali dan mempertahankan preview', async () => {
    const card = uploader();
    card.skipOptional();
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
