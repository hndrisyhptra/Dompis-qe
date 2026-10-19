import assert from 'node:assert/strict';
import test from 'node:test';
import { parseCoordinates, technicianLocation } from '../../resources/js/technician-location.js';

test('tempel latitude-longitude, long-lat Indonesia, koma, spasi dan titik koma', () => {
    for (const text of ['-7.257472, 112.752090', '112.752090,-7.257472', '-7.257472 112.752090', '(-7.257472; 112.752090)', '  -7.257472\n112.752090  ']) {
        assert.deepEqual(parseCoordinates(text), { latitude: '-7.2574720', longitude: '112.7520900' });
    }
    assert.deepEqual(parseCoordinates('0, 0'), { latitude: '0.0000000', longitude: '0.0000000' });
    assert.deepEqual(parseCoordinates('-90, 180'), { latitude: '-90.0000000', longitude: '180.0000000' });
    // Jika keduanya dalam rentang latitude, urutan mengikuti label: latitude dahulu.
    assert.deepEqual(parseCoordinates('45, 80'), { latitude: '45.0000000', longitude: '80.0000000' });
});

test('koordinat kosong, tidak lengkap, di luar rentang, URL dan desimal koma ditolak', () => {
    for (const text of ['', ' ', ',', '-7', '91,112', '-7,181', 'Infinity,0', 'NaN,0', '0x10,0', '1e2,0', 'abc,112', '-7,112,3', '-7,257472,112,752090', 'https://maps.google.com/?q=-7,112', '<script>,0']) {
        assert.equal(parseCoordinates(text), null, text);
    }
});

test('paste memisahkan koordinat tanpa request server dan tetap perlu Simpan lokasi', () => {
    const card = technicianLocation({}, null);
    assert.equal(card.canSave, false);
    assert.equal(card.mapUrl, null);
    card.coordinates = '112.752090, -7.257472';
    card.applyCoordinates();
    assert.equal(card.latitude, '-7.2574720');
    assert.equal(card.longitude, '112.7520900');
    assert.equal(card.source, 'manual');
    assert.equal(card.accuracy, '');
    assert.equal(card.dirty, true);
    assert.equal(card.canSave, true);
    assert.match(card.mapUrl, /^https:\/\/maps.google.com\/\?q=-7.2574720%2C112.7520900$/);
});

test('paste tidak valid tidak merusak koordinat tersimpan atau dianggap tersimpan', () => {
    const card = technicianLocation({ latitude: -7, longitude: 112, source: 'gps', accuracy: 5 }, null);
    assert.equal(card.dirty, false);
    assert.equal(technicianLocation({ latitude: -7, longitude: 112, dirty: true }, null).dirty, true);
    card.coordinates = 'salah';
    card.applyCoordinates();
    assert.equal(card.latitude, '-7');
    assert.equal(card.longitude, '112');
    assert.equal(card.accuracy, '5');
    assert.equal(card.canSave, false);
    assert.ok(card.coordinateError);
    card.coordinates = '';
    card.applyCoordinates();
    assert.equal(card.canSave, true);
});

test('edit manual membatalkan ketelitian GPS lama dan koordinat nol tetap valid', () => {
    const card = technicianLocation({ latitude: -7, longitude: 112, source: 'gps', accuracy: 5 }, null);
    card.latitude = '0';
    card.longitude = '0';
    card.manualChanged();
    assert.equal(card.source, 'manual');
    assert.equal(card.accuracy, '');
    assert.equal(card.dirty, true);
    assert.equal(card.canSave, true);
    assert.ok(card.mapUrl);
    card.latitude = '';
    assert.equal(card.canSave, false);
    card.latitude = '-91';
    assert.equal(card.canSave, false);
});

test('GPS memakai koordinat valid, loading, timeout dan tidak menghapus koordinat ketika gagal', () => {
    let success, failure, calls = 0;
    const gps = { getCurrentPosition: (ok, fail, options) => {
        success = ok;
        failure = fail;
        calls++;
        assert.equal(options.timeout, 15000);
    } };
    const card = technicianLocation({}, gps);
    card.locate();
    card.locate();
    assert.equal(calls, 1);
    assert.equal(card.locating, true);
    success({ coords: { latitude: 0, longitude: 112, accuracy: 5.5 } });
    assert.equal(card.latitude, '0.0000000');
    assert.equal(card.source, 'gps');
    assert.equal(card.accuracy, '5.50');
    assert.equal(card.canSave, true);
    card.locate();
    failure();
    assert.equal(card.latitude, '0.0000000');
    assert.equal(card.locating, false);
    assert.match(card.error, /lanjut tanpa lokasi/);
});

test('respons GPS lama tidak menimpa hasil paste atau edit manual', () => {
    for (const mode of ['paste', 'manual']) {
        let success;
        const card = technicianLocation({}, { getCurrentPosition: ok => { success = ok; } });
        card.locate();
        if (mode === 'paste') {
            card.coordinates = '-7,112';
            card.applyCoordinates();
        } else {
            card.latitude = '-7';
            card.longitude = '112';
            card.manualChanged();
        }
        success({ coords: { latitude: -8, longitude: 115, accuracy: 3 } });
        assert.equal(Number(card.latitude), -7);
        assert.equal(Number(card.longitude), 112);
        assert.equal(card.source, 'manual');
    }
});

test('GPS tidak tersedia atau melempar error tetap memungkinkan input manual', () => {
    for (const gps of [null, { getCurrentPosition: () => { throw new Error('Denied'); } }]) {
        const card = technicianLocation({}, gps);
        card.locate();
        assert.equal(card.locating, false);
        assert.match(card.error, /lanjut tanpa lokasi/);
        card.coordinates = '-7,112';
        card.applyCoordinates();
        assert.equal(card.error, '');
        assert.equal(card.canSave, true);
    }
});
