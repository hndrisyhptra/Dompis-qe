import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/copyable-value.js', import.meta.url), 'utf8');

function component(value, clipboard) {
    let reset;
    const context = vm.createContext({
        navigator: { clipboard },
        setTimeout: callback => { reset = callback; return 1; },
        clearTimeout: () => {},
    });
    vm.runInContext(source.replace('export function', 'function'), context);
    return { card: context.copyableValue(value), reset: () => reset() };
}

test('salin IHLD mempertahankan nol depan dan feedback selesai hilang setelah jeda', async () => {
    let copied;
    const state = component('0000123', { writeText: async value => { copied = value; } });
    await state.card.copy();
    assert.equal(copied, '0000123');
    assert.equal(state.card.copied, true);
    assert.equal(state.card.copying, false);
    state.reset();
    assert.equal(state.card.copied, false);
});

test('clipboard tidak tersedia atau ditolak menampilkan kegagalan tanpa menghilangkan identifier', async () => {
    for (const clipboard of [undefined, { writeText: async () => { throw new Error('Denied'); } }]) {
        const { card } = component('INC123', clipboard);
        await card.copy();
        assert.equal(card.failed, true);
        assert.equal(card.copied, false);
        assert.equal(card.copying, false);
        assert.equal(card.value, 'INC123');
    }
});

test('klik ganda hanya menjalankan satu salinan dan dapat dicoba ulang setelah gagal', async () => {
    let calls = 0;
    let reject;
    const clipboard = { writeText: () => { calls++; return new Promise((_, fail) => { reject = fail; }); } };
    const { card } = component('INC123', clipboard);
    const pending = card.copy();
    await card.copy();
    assert.equal(calls, 1);
    reject(new Error('Denied'));
    await pending;
    assert.equal(card.failed, true);
    clipboard.writeText = async () => {};
    await card.copy();
    assert.equal(card.failed, false);
    assert.equal(card.copied, true);
});
