import assert from 'node:assert/strict';
import test from 'node:test';
import { baBongkarForm, signaturePad } from '../../resources/js/ba-bongkar.js';

test('keputusan kosong tidak dianggap Tidak dan nilai radio dipulihkan dengan benar', () => {
    for (const [input, expected] of [[null, ''], [false, '0'], ['0', '0'], [true, '1'], ['1', '1']]) {
        assert.equal(baBongkarForm(input, [], {}).choice, expected);
    }
});

test('beberapa rincian satu jenis tetap terpisah dan pergantian kelompok mengganti tipe', () => {
    const form = baBongkarForm(true, [], { distribusi: { types: ['48 Core', '12 Core'] }, tiang: { types: ['T7', 'T9'] } });
    form.add(); form.add();
    form.rows[0].length = 190;
    form.rows[1].group = 'tiang';
    form.change(form.rows[1]);
    assert.equal(form.rows[1].type, 'T7');
    assert.equal(form.rows[0].length, 190);
    assert.equal(form.rows[1].length, '');
});

test('TTD bertahan saat resize, hapus mengosongkan payload, dan gambar mengganti upload', () => {
    const calls = [];
    const context = { fillRect() {}, beginPath() {}, moveTo() {}, lineTo() {}, stroke() { calls.push('stroke'); } };
    const pad = signaturePad();
    pad.$refs = {
        canvas: { clientWidth: 300, getBoundingClientRect: () => ({ left: 0, top: 0, width: 300, height: 180 }), getContext: () => context, setPointerCapture() {}, toDataURL: () => 'data:image/png;base64,drawn' },
        value: { value: '' }, upload: { value: 'old-signature.png' },
    };
    pad.start({ pointerId: 1, button: 0, clientX: 10, clientY: 20 });
    pad.move({ clientX: 100, clientY: 80 });
    pad.end();
    assert.equal(pad.$refs.upload.value, '');
    assert.equal(pad.$refs.value.value, 'data:image/png;base64,drawn');
    pad.$refs.canvas.clientWidth = 500;
    pad.redraw();
    assert.equal(pad.strokes.length, 1);
    assert.equal(pad.$refs.value.value, 'data:image/png;base64,drawn');
    assert.ok(calls.length >= 3);
    pad.clear();
    assert.equal(pad.$refs.value.value, '');
    assert.equal(pad.strokes.length, 0);
});
