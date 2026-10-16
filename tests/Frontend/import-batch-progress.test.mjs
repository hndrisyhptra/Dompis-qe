import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const code = source.slice(source.indexOf('window.importBatchProgress ='), source.indexOf('window.dashboardMonitoring ='));
function progress(options = {}, response = {}) {
    const window = { location: { reload: () => { throw new Error('unexpected reload'); } } };
    vm.runInNewContext(code, { window, fetch: async () => ({ ok: true, json: async () => response }), clearTimeout: () => {}, setTimeout: () => 1 });
    return window.importBatchProgress(options);
}

test('progress legacy tanpa skipped tetap berfungsi dan AISS menampilkan jumlah dilewati', () => {
    assert.equal(progress().skipped, 0);
    assert.equal(progress({ skipped: 7, status: 'partial' }).skipped, 7);
    assert.equal(progress({ status: 'partial' }).statusLabel, 'Selesai sebagian');
});

test('polling AISS memuat progress termasuk baris skip tanpa menghitung ulang persentase di browser', async () => {
    const state = progress({}, { status: 'processing', total_rows: 31, success_rows: 13, failed_rows: 0, skipped_rows: 12, processed_rows: 25, percentage: 81, finished: false });
    await state.refresh();
    assert.equal(state.skipped, 12);
    assert.equal(state.success, 13);
    assert.equal(state.processed, 25);
    assert.equal(state.percentage, 81);
    assert.equal(state.finished, false);
});

test('batch selesai dengan skip mencapai 100 persen dan respons legacy tidak menyisakan counter AISS', async () => {
    const state = progress({}, { status: 'partial', total_rows: 5, success_rows: 2, failed_rows: 0, skipped_rows: 3, processed_rows: 5, percentage: 100, finished: true });
    await state.refresh();
    assert.equal(state.skipped, 3);
    assert.equal(state.finished, true);
    assert.equal(state.percentage, 100);
    const legacy = progress({ skipped: 99 }, { status: 'completed', success_rows: 2, percentage: 100, finished: true });
    await legacy.refresh();
    assert.equal(legacy.skipped, 0);
});
