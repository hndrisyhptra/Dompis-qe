import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const component = source.slice(source.indexOf('window.boqEditor ='), source.indexOf('window.importUploadForm ='));
const encode = value => Buffer.from(JSON.stringify(value)).toString('base64');
const options = [
    { id: 1, code: 'M-KEEP', name: 'Kabel feeder', unit: 'meter', type: 'MATERIAL' },
    { id: 2, code: 'M-REMOVE', name: 'ODP tambahan', unit: 'unit', type: 'MATERIAL' },
    { id: 3, code: 'J-KEEP', name: 'Jasa pasang', unit: 'meter', type: 'JASA' },
];
function editor(settings = {}) {
    const window = {};
    vm.runInNewContext(component, { window, atob, btoa, document: { querySelector: () => null } });
    return window.boqEditor(encode([
        { designator_id: 1, qty: 10, qty_actual: 7, existing: true, can_delete: false },
        { designator_id: 2, qty: 3, qty_actual: null, existing: true, can_delete: true },
    ]), encode(options), settings);
}

test('editor reservasi hanya mencari material sementara editor plan tetap mencakup jasa', () => {
    const actual = editor({ materialOnly: true, maxItems: 100 });
    assert.deepEqual(Array.from(actual.options, value => value.code), ['M-KEEP', 'M-REMOVE']);
    assert.deepEqual(Array.from(actual.filteredOptions('feeder', ''), value => value.code), ['M-KEEP']);
    assert.equal(actual.filteredOptions('Jasa', '').length, 0);
    assert.equal(editor().options.length, 3);
});

test('hapus memerlukan konfirmasi dan tidak menghapus item yang sudah aktual/evidence', () => {
    const actual = editor({ materialOnly: true });
    actual.requestDrop(0);
    assert.equal(actual.dropConfirm, false);
    actual.requestDrop(1);
    assert.equal(actual.dropConfirm, true);
    assert.equal(actual.items.length, 2);
    actual.confirmDrop();
    assert.equal(actual.items.length, 1);
    assert.equal(actual.items[0].qty_actual, 7);
    // Memalsukan indeks di client tetap tidak boleh melewati proteksi UI.
    actual.add();
    actual.pendingDropIndex = 0;
    actual.confirmDrop();
    assert.equal(actual.items.length, 2);
});

test('tambah, pilih, cegah duplikasi, dan batas item tetap bekerja', () => {
    const actual = editor({ materialOnly: true, maxItems: 3 });
    actual.add();
    assert.equal(actual.items.length, 3);
    assert.equal(actual.items[2].open, true);
    assert.equal(actual.items[2].qty, 1);
    assert.equal(actual.optionUsedByOther(1, 2), true);
    actual.add();
    assert.equal(actual.items.length, 3);
    actual.selectOption(actual.items[2], { id: 10 });
    assert.equal(actual.items[2].designator_id, '10');
    assert.equal(actual.items[2].open, false);
});

test('batal/open ulang mengembalikan data asli dan membatalkan semua konfirmasi', () => {
    const actual = editor({ materialOnly: true });
    actual.openEdit();
    actual.items[0].qty = 25;
    actual.saveConfirm = true;
    actual.requestDrop(1);
    actual.closeEdit();
    assert.equal(actual.edit, false);
    assert.equal(actual.saveConfirm, false);
    assert.equal(actual.dropConfirm, false);
    actual.openEdit();
    assert.equal(actual.items[0].qty, 10);
    assert.equal(actual.items[0].can_delete, false);
    assert.equal(actual.items[0].qty_actual, 7);
});
