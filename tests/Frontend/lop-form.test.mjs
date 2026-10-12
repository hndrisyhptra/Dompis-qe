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
