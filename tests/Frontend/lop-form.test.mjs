import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const component = source.slice(source.indexOf('const emptyDatek ='), source.indexOf('window.lopReportModal ='));

function form(program) {
    const window = { addEventListener() {}, innerWidth: 400 };
    vm.runInNewContext(component, { window, URLSearchParams });
    const model = window.lopForm({
        initial: { program_type: program, incident: 'INC-TEST', sto: 'SDA', area: '3' },
        maxSegments: 5,
        programCodes: { recovery: 'QEREC', preventive: 'QEPREV', relok_utilitas: 'QEREL', relok_tiang: 'QERELTIANG' },
        segmentLabels: { feeder: 'Feeder', distribusi: 'Distribusi', odc: 'ODC', odp: 'ODP', tiang: 'Tiang', otb: 'OTB' },
    });
    model.$watch = () => {};
    model.$nextTick = callback => callback();
    model.init();
    // Relok Tiang dapat memberi default Tiang; mulai dari daftar kosong untuk batas.
    model.form.segments = [];
    return model;
}

for (const program of ['recovery', 'preventive', 'relok_utilitas', 'relok_tiang']) {
    test(`${program}: pilih lima, blokir keenam, hapus lalu tambah kembali`, () => {
        const model = form(program);
        for (const segment of ['feeder', 'distribusi', 'odc', 'odp', 'tiang']) model.toggleSegment(segment);
        assert.deepEqual(Array.from(model.form.segments), ['feeder', 'distribusi', 'odc', 'odp', 'tiang']);
        model.toggleSegment('otb');
        assert.equal(model.form.segments.length, 5);
        assert.ok(!model.isSegmentSelected('otb'));
        assert.match(model.form.nama_lop, /FEEDER_DISTRIBUSI_ODC_ODP_TIANG/);
        model.toggleSegment('odc');
        model.toggleSegment('otb');
        assert.deepEqual(Array.from(model.form.segments), ['feeder', 'distribusi', 'odp', 'tiang', 'otb']);
        model.segmentQuery = 'tian';
        assert.deepEqual(Array.from(model.filteredSegments(), option => option.value), ['tiang']);
    });
}

test('mengganti program mempertahankan lima segmen dan mengikuti budget otomatis', () => {
    const model = form('recovery');
    model.form.segments = ['feeder', 'distribusi', 'odc', 'odp', 'tiang'];
    for (const program of ['preventive', 'relok_utilitas', 'relok_tiang', 'recovery']) {
        model.form.program_type = program;
        model.syncProgramRules();
        assert.equal(model.form.segments.length, 5);
        assert.equal(model.form.budget_type, program === 'relok_utilitas' ? 'CAPEX' : 'OPEX');
        assert.match(model.form.nama_lop, /FEEDER_DISTRIBUSI_ODC_ODP_TIANG/);
    }
});

function incidentForm(fetch, validateIncident = true) {
    const window = { addEventListener() {}, innerWidth: 400 };
    vm.runInNewContext(component, { window, URLSearchParams, fetch });
    const model = window.lopForm({
        validateIncident,
        incidentTicketTypes: { recovery: 'GAMAS, UNKNOWN, TSEL, atau PREMAN', preventive: 'GAMAS, UNKNOWN, INFRACARE, atau PREMAN', relok_tiang: 'NON NUMBERING', relok_utilitas: null },
        initial: { program_type: 'recovery', branch: 'SIDOARJO', sto: 'SDA', area: '3' },
    });
    const watchers = {};
    model.$watch = (field, callback) => { watchers[field] = callback; };
    model.$nextTick = callback => callback();
    model.init();
    return { model, watchers };
}

test('pengecekan memakai program terpilih dan pergantian program mengecek incident yang sama lagi', async () => {
    const queries = [];
    const { model, watchers } = incidentForm(async url => {
        queries.push(url);
        const isTiang = url.includes('program_type=relok_tiang');
        return { ok: true, json: async () => ({ found: true, incident_validation: { valid: !isTiang, message: isTiang ? 'Wajib NON NUMBERING' : 'GAMAS sesuai' } }) };
    });
    model.form.incident = 'INC-TEST';
    await model.lookupTicket();
    assert.equal(model.lookup.validation.valid, true);
    model.form.program_type = 'relok_tiang';
    watchers['form.program_type']();
    await new Promise(setImmediate);
    assert.equal(queries.length, 2);
    assert.match(queries[1], /program_type=relok_tiang/);
    assert.equal(model.lookup.validation.valid, false);
    assert.equal(model.lookup.mode, null);
    assert.equal(model.lookup.message, 'Wajib NON NUMBERING');
});

test('respons incident atau program lama tidak menimpa hasil pengecekan terbaru', async () => {
    const pending = [];
    const { model } = incidentForm(url => new Promise(resolve => pending.push({ url, resolve })));
    model.form.incident = 'INC-OLD';
    const old = model.lookupTicket();
    model.form.incident = 'INC-NEW';
    model.form.program_type = 'relok_tiang';
    model.resetIncidentLookup();
    const current = model.lookupTicket();
    pending[1].resolve({ ok: true, json: async () => ({ found: true, sto: 'NEW', incident_validation: { valid: false, message: 'Jenis tiket salah' } }) });
    await current;
    pending[0].resolve({ ok: true, json: async () => ({ found: true, sto: 'OLD', incident_validation: { valid: true, message: 'Sesuai' } }) });
    await old;
    assert.equal(model.lookup.validation.valid, false);
    assert.equal(model.form.sto, 'NEW');
    assert.equal(model.lookup.message, 'Jenis tiket salah');
    assert.equal(model.lookup.loading, false);
});

test('incident kosong menghasilkan INP dan memperbarui nama sebelum submit, INP tidak dicek sebagai tiket eksternal', async () => {
    const queries = [];
    const { model } = incidentForm(async url => {
        queries.push(url);
        return { ok: true, json: async () => ({ ok: true, incident: 'INP123456' }) };
    });
    let prevented = 0;
    let submitted = 0;
    const event = { preventDefault() { prevented++; }, target: { requestSubmit() { submitted++; } } };
    await model.submitLop(event);
    assert.equal(prevented, 1);
    assert.equal(submitted, 1);
    assert.equal(model.form.incident, 'INP123456');
    assert.match(model.form.nama_lop, /INP123456/);
    await model.lookupTicket();
    assert.equal(queries.length, 1);
    assert.match(queries[0], /manual-incident/);
    await model.submitLop(event);
    assert.equal(prevented, 1);
});

test('hasil tidak valid menghalangi submit dan kegagalan server bisa dicek ulang', async () => {
    let calls = 0;
    const { model } = incidentForm(async () => {
        calls++;
        return { ok: true, json: async () => ({ found: false, error: true, incident_validation: { valid: false, message: 'Pengecekan gagal' } }) };
    });
    model.form.incident = 'INC-TEST';
    await model.lookupTicket();
    await model.lookupTicket();
    assert.equal(calls, 2);
    let prevented = false;
    await model.submitLop({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
});

test('form edit tidak menjalankan validasi incident baru atau generate saat submit', async () => {
    const { model, watchers } = incidentForm(() => { throw new Error('Tidak boleh fetch'); }, false);
    model.form.program_type = 'relok_tiang';
    watchers['form.program_type']();
    model.lookup.lastQuery = 'tetap';
    watchers['form.incident']();
    assert.equal(model.lookup.lastQuery, 'tetap');
    await model.submitLop({ preventDefault() { throw new Error('Submit edit tidak boleh dicegat'); } });
});

test('REG ditemukan sebelum program dipilih, lalu Recovery tetap mengunci Simpan sampai valid', async () => {
    const { model, watchers } = incidentForm(async url => {
        const selected = url.includes('program_type=recovery');
        return { ok: true, json: async () => ({
            found: true, jenis_tiket_2: 'REG', sto: 'KPS',
            incident_validation: selected ? { valid: false, message: 'Gunakan tiket GAMAS, UNKNOWN, TSEL, atau PREMAN untuk QE Recovery. Tiket ini berjenis REG.' } : undefined,
        }) };
    });
    model.form.program_type = '';
    model.form.incident = 'INC47206386';
    assert.equal(model.canSubmitLop, false);
    await model.lookupTicket();
    assert.equal(model.canSubmitLop, false);
    assert.equal(model.lookup.mode, 'pending');
    model.form.program_type = 'recovery';
    assert.equal(model.canSubmitLop, false);
    watchers['form.program_type']();
    await new Promise(setImmediate);
    assert.equal(model.canSubmitLop, false);
    assert.match(model.lookup.message, /REG/);
    let prevented = false;
    await model.submitLop({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
});

test('Simpan hanya aktif setelah true untuk incident dan program yang sama, perubahan langsung menguncinya', async () => {
    let resolve;
    const { model, watchers } = incidentForm(() => new Promise(done => { resolve = done; }));
    model.form.incident = 'INC-GAMAS';
    assert.equal(model.canSubmitLop, false);
    let prevented = false;
    await model.submitLop({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    const checking = model.lookupTicket();
    assert.equal(model.canSubmitLop, false);
    resolve({ ok: true, json: async () => ({ found: true, incident_validation: { valid: true, message: 'GAMAS sesuai' } }) });
    await checking;
    assert.equal(model.canSubmitLop, true);
    model.form.incident = 'INC-OTHER';
    assert.equal(model.canSubmitLop, false);
    model.form.incident = 'INC-GAMAS';
    watchers['form.incident']();
    assert.equal(model.canSubmitLop, false);
    const repeated = model.lookupTicket();
    resolve({ ok: true, json: async () => ({ found: true, incident_validation: { valid: true } }) });
    await repeated;
    model.form.program_type = 'preventive';
    assert.equal(model.canSubmitLop, false);
});

test('respons tanpa validasi, HTTP gagal, atau jaringan gagal tidak membuka tombol Simpan', async () => {
    for (const fetch of [
        async () => ({ ok: true, json: async () => ({ found: true, jenis_tiket_2: 'GAMAS' }) }),
        async () => ({ ok: false }),
        async () => { throw new Error('offline'); },
    ]) {
        const { model } = incidentForm(fetch);
        model.form.incident = 'INC-TEST';
        await model.lookupTicket();
        assert.equal(model.canSubmitLop, false);
        assert.notEqual(model.lookup.mode, 'found');
    }
});

test('incident kosong, INP dan Utilitas mempertahankan pengecualian; program kosong tetap mengunci Simpan', () => {
    const { model } = incidentForm(() => { throw new Error('Tidak perlu fetch'); });
    assert.equal(model.canSubmitLop, true);
    model.form.incident = 'INP123456';
    assert.equal(model.canSubmitLop, true);
    model.form.incident = 'INC-UTILITY';
    assert.equal(model.canSubmitLop, false);
    model.form.program_type = 'relok_utilitas';
    assert.equal(model.canSubmitLop, true);
    model.form.program_type = '';
    assert.equal(model.canSubmitLop, false);
});

test('UNKNOWN membuka Simpan untuk Recovery dan Preventive setelah hasil server menyatakan valid', async () => {
    for (const program of ['recovery', 'preventive']) {
        const { model } = incidentForm(async () => ({ ok: true, json: async () => ({
            found: true, jenis_tiket_2: 'UNKNOWN', incident_validation: { valid: true, ticket_type: 'UNKNOWN', message: 'Tiket sesuai.' },
        }) }));
        model.form.program_type = program;
        model.form.incident = 'INC-UNKNOWN';
        assert.equal(model.canSubmitLop, false);
        await model.lookupTicket();
        assert.equal(model.canSubmitLop, true);
        assert.equal(model.lookup.mode, 'found');
    }
});

test('INFRACARE membuka Simpan setelah validasi Preventive dan terkunci saat berganti ke Recovery', async () => {
    const { model, watchers } = incidentForm(async url => ({ ok: true, json: async () => ({
        found: true, jenis_tiket_2: 'INFRACARE',
        incident_validation: { valid: url.includes('program_type=preventive'), ticket_type: 'INFRACARE', message: 'Hasil validasi tiket.' },
    }) }));
    model.form.program_type = 'preventive';
    model.form.incident = 'INC-INFRA';
    assert.equal(model.canSubmitLop, false);
    await model.lookupTicket();
    assert.equal(model.canSubmitLop, true);
    assert.equal(model.lookup.mode, 'found');
    model.form.program_type = 'recovery';
    assert.equal(model.canSubmitLop, false);
    watchers['form.program_type']();
    await new Promise(setImmediate);
    assert.equal(model.lookup.validation.valid, false);
    assert.equal(model.canSubmitLop, false);
});
