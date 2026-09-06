import './bootstrap';

import Alpine from 'alpinejs';
import imageCompression from 'browser-image-compression';

const COMPRESS_OPTS = { maxWidthOrHeight: 1920, maxSizeMB: 1, initialQuality: 0.72, fileType: 'image/webp', useWebWorker: true };
const THUMB_OPTS = { maxWidthOrHeight: 360, maxSizeMB: 0.06, initialQuality: 0.6, fileType: 'image/webp', useWebWorker: true };

const isImageFile = (file) => file.type.startsWith('image/');

/**
 * Kompres 1 gambar -> File webp. Non-gambar (PDF) dikembalikan apa adanya.
 * Bila kompresi gagal (browser tua / gambar rusak), pakai file asli.
 */
async function compressImage(file, opts) {
    if (!isImageFile(file)) return file;
    try {
        const blob = await imageCompression(file, opts);
        const name = file.name.replace(/\.[^.]+$/, '') + '.webp';
        return new File([blob], name, { type: 'image/webp', lastModified: Date.now() });
    } catch (e) {
        return file;
    }
}

async function makeThumb(file) {
    if (!isImageFile(file)) return null;
    try {
        const blob = await imageCompression(file, THUMB_OPTS);
        return new File([blob], 'thumb.webp', { type: 'image/webp' });
    } catch (e) {
        return null;
    }
}

window.evidenceUploader = (options = {}) => ({
    expanded: options.expanded ?? true,
    endpoint: options.endpoint ?? null,     // set => mode antrean async per-file
    meta: {
        category: options.category ?? null,
        type: options.type ?? 'PHOTO',
        designator_id: options.designatorId ?? null,
        note: options.note ?? null,
        latitude: options.latitude ?? null,
        longitude: options.longitude ?? null,
    },
    files: [],        // mode form (replace / no-JS fallback)
    queue: [],        // mode antrean
    running: 0,
    preview: null,

    get queueActive() { return this.queue.length > 0; },
    get queueBusy() { return this.queue.some((q) => q.status === 'compressing' || q.status === 'uploading' || q.status === 'queued'); },
    get queueFailed() { return this.queue.some((q) => q.status === 'error'); },

    // ---- mode antrean (endpoint) --------------------------------------

    async selectQueue(event) {
        const picked = Array.from(event.target.files);
        event.target.value = '';
        for (const file of picked) {
            const item = {
                id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                name: file.name,
                origSize: file.size,
                size: file.size,
                isImage: isImageFile(file),
                previewUrl: isImageFile(file) ? URL.createObjectURL(file) : null,
                status: 'queued',
                progress: 0,
                error: '',
                raw: file,
            };
            this.queue.push(item);
        }
        this.pump();
    },

    pump() {
        while (this.running < 3) {
            const next = this.queue.find((q) => q.status === 'queued');
            if (!next) break;
            this.running++;
            this.uploadItem(next).finally(() => {
                this.running--;
                this.pump();
                this.maybeFinish();
            });
        }
    },

    async uploadItem(item) {
        try {
            item.status = 'compressing';
            const file = await compressImage(item.raw, COMPRESS_OPTS);
            const thumb = await makeThumb(file);
            item.size = file.size;

            item.status = 'uploading';
            item.progress = 0;
            const evidence = await this.xhrUpload(file, thumb, (pct) => { item.progress = pct; });
            item.status = 'done';
            item.progress = 100;
            item.evidence = evidence;
        } catch (e) {
            item.status = 'error';
            item.error = e?.message || 'Upload gagal';
        }
    },

    retry(item) {
        item.status = 'queued';
        item.error = '';
        item.progress = 0;
        this.pump();
    },

    removeQueued(item) {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
        this.queue = this.queue.filter((q) => q !== item);
        this.maybeFinish();
    },

    maybeFinish() {
        if (this.queue.length && this.queue.every((q) => q.status === 'done')) {
            // Semua sukses -> muat ulang supaya galeri server ter-render dengan evidence baru.
            window.location.reload();
        }
    },

    xhrUpload(file, thumb, onProgress) {
        return new Promise((resolve, reject) => {
            const form = new FormData();
            form.append('file', file, file.name);
            if (thumb) form.append('thumb', thumb, 'thumb.webp');
            form.append('category', this.meta.category ?? '');
            form.append('type', this.meta.type ?? 'PHOTO');
            if (this.meta.designator_id) form.append('designator_id', this.meta.designator_id);
            if (this.meta.note) form.append('note', this.meta.note);
            if (this.meta.latitude != null) form.append('latitude', this.meta.latitude);
            if (this.meta.longitude != null) form.append('longitude', this.meta.longitude);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.endpoint);
            xhr.setRequestHeader('X-CSRF-TOKEN', window.CSRF_TOKEN || '');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText)); } catch (_) { resolve({}); }
                } else if (xhr.status === 422) {
                    let msg = 'File ditolak server';
                    try { const j = JSON.parse(xhr.responseText); msg = j.message || Object.values(j.errors || {})[0]?.[0] || msg; } catch (_) {}
                    reject(new Error(msg));
                } else {
                    reject(new Error(`Gagal (${xhr.status})`));
                }
            };
            xhr.onerror = () => reject(new Error('Jaringan bermasalah'));
            xhr.ontimeout = () => reject(new Error('Timeout'));
            xhr.send(form);
        });
    },

    // ---- mode form (replace / fallback) -----------------------------

    async selectFiles(event) {
        this.releasePreviews();
        const picked = Array.from(event.target.files);
        this.files = picked.map((file) => ({
            file,
            name: file.name,
            size: file.size,
            type: file.type,
            isImage: isImageFile(file),
            url: isImageFile(file) ? URL.createObjectURL(file) : null,
            compressing: isImageFile(file),
        }));
        // Kompres di belakang layar lalu sinkronkan ke <input> untuk submit native.
        await Promise.all(this.files.map(async (item) => {
            if (!item.isImage) return;
            item.file = await compressImage(item.file, COMPRESS_OPTS);
            item.size = item.file.size;
            item.compressing = false;
        }));
        this.syncInput();
    },

    removeFile(index) {
        const removed = this.files[index];
        if (removed?.url) URL.revokeObjectURL(removed.url);
        if (this.preview === removed) this.preview = null;
        this.files.splice(index, 1);
        this.syncInput();
    },

    syncInput() {
        const transfer = new DataTransfer();
        this.files.forEach((item) => transfer.items.add(item.file));
        this.$refs.input.files = transfer.files;
    },

    // ---- preview modal (dipakai kedua mode) ------------------------

    openPreview(item) {
        this.preview = { url: item.url ?? item.previewUrl, name: item.name, size: item.size, isImage: item.isImage };
        document.body.classList.add('overflow-hidden');
    },

    openStoredPreview(url, name, mime, size) {
        this.preview = {
            url,
            name,
            size: Number(size || 0),
            type: mime || '',
            isImage: (mime || '').startsWith('image/'),
            persisted: true,
        };
        document.body.classList.add('overflow-hidden');
    },

    closePreview() {
        this.preview = null;
        document.body.classList.remove('overflow-hidden');
    },

    releasePreviews() {
        this.files.forEach((item) => { if (item.url) URL.revokeObjectURL(item.url); });
    },

    formatSize(bytes) {
        if (!bytes) return '—';
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
});

const emptyDatek = () => ({
    kategori: '', odc: [], odp: [], gpon: [], kabel: [], ip: [],
    olt: false, rca: '', est: '', pic: { nama: '', telp: '' },
});

const normalizeDatek = (raw) => {
    const d = { ...emptyDatek(), ...(raw && typeof raw === 'object' ? raw : {}) };
    const list = (v) => (Array.isArray(v) ? v.map(String) : []);
    d.odc = list(d.odc);
    d.odp = list(d.odp);
    d.kabel = list(d.kabel);
    d.ip = list(d.ip);
    d.gpon = Array.isArray(d.gpon)
        ? d.gpon.map((g) => ({
            name: String(g?.name ?? ''),
            ip: g?.ip ? String(g.ip) : '',
            ports: Array.isArray(g?.ports) ? g.ports.map(String) : [],
        }))
        : [];
    d.olt = Boolean(d.olt);
    d.kategori = d.kategori == null ? '' : String(d.kategori);
    d.rca = d.rca == null ? '' : String(d.rca);
    d.est = d.est == null ? '' : String(d.est);
    d.pic = { nama: String(d.pic?.nama ?? ''), telp: String(d.pic?.telp ?? '') };
    return d;
};

window.lopForm = (options = {}) => ({
    form: {
        incident: '', sto: '', branch: '', area: '3', segment: '',
        program_type: '', budget_type: '', job_description: '', ticket_summary: '', ihld_id: '', nama_lop: '',
        ...(options.initial ?? {}),
        datek: normalizeDatek(options.initial?.datek),
    },
    datekText: { odc: '', odp: '', gpon: '', kabel: '' },
    datekParsing: false,
    template: options.template ?? '{area}{sto}_{program_code}_{incident}_{segment}',
    programCodes: options.programCodes ?? {},
    segmentLabels: options.segmentLabels ?? {},
    nameManuallyEdited: false,
    lookup: {
        loading: false, error: false, notFound: false, message: '', warnings: [], lastQuery: null,
        mode: null, summaryTitle: '', summaryText: '', duplicate: null,
    },
    manualGen: { loading: false, message: '' },

    init() {
        ['incident', 'sto', 'branch', 'area', 'segment', 'program_type', 'budget_type', 'job_description']
            .forEach((field) => this.$watch(`form.${field}`, () => this.regenerateName()));
        if (!this.form.nama_lop) this.regenerateName(true);
        this.hydrateDatekText();
    },

    regenerateName(force = false) {
        if (this.nameManuallyEdited && !force) return;
        if (this.form.program_type !== 'relok_utilitas') this.form.budget_type = '';

        const values = {
            '{area}': this.token(this.form.area, true),
            '{sto}': this.token(this.form.sto, true),
            '{branch}': this.token(this.form.branch, true),
            '{segment}': this.token(this.form.segment, true),
            '{program}': this.token(this.programLabel(this.form.program_type), true),
            '{program_code}': this.programCodes[this.form.program_type] ?? '',
            '{budget_type}': this.token(this.form.budget_type, true),
            '{incident}': this.token(this.form.incident, true),
            '{description}': this.token(this.form.job_description),
        };

        this.form.nama_lop = Object.entries(values)
            .reduce((name, [placeholder, value]) => name.split(placeholder).join(value), this.template)
            .replace(/_+/g, '_').replace(/^_+|_+$/g, '');
    },

    resetGeneratedName() {
        this.nameManuallyEdited = false;
        this.regenerateName(true);
    },

    async lookupTicket() {
        const incident = String(this.form.incident ?? '').trim();
        if (!incident || this.lookup.loading || incident === this.lookup.lastQuery) return;

        this.lookup.loading = true;
        this.lookup.error = false;
        this.lookup.notFound = false;
        this.lookup.message = '';
        this.lookup.warnings = [];
        this.lookup.duplicate = null;
        this.resetLookupSummary();
        this.manualGen.message = '';

        try {
            const res = await fetch(`/lop/ticket-lookup?incident=${encodeURIComponent(incident)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                this.lookup.error = true;
                this.lookup.message = 'Gagal mengambil data tiket. Isi field secara manual.';
                return;
            }

            const json = await res.json();
            this.lookup.lastQuery = incident;
            this.lookup.duplicate = json.existing_lop || null;

            if (!json.found) {
                this.lookup.error = Boolean(json.error);
                this.lookup.notFound = !json.error;
                this.lookup.message = json.message
                    || 'Tiket tidak ditemukan di database. Buat nomor tiket manual di bawah.';
                return;
            }

            if (json.sto) this.form.sto = json.sto;
            if (json.branch) this.form.branch = json.branch;
            if (json.segment) this.form.segment = json.segment;
            this.form.ticket_summary = json.summary || '';
            this.applyDatek(json.datek);

            this.lookup.warnings = Array.isArray(json.warnings) ? json.warnings : [];
            this.lookup.mode = 'found';
            this.lookup.summaryTitle = 'Tiket ditemukan di database';
            this.lookup.summaryText = 'STO, Branch, dan Segmen terisi otomatis — masih bisa diubah.';
            this.regenerateName();
        } catch (e) {
            this.lookup.error = true;
            this.lookup.message = 'Terjadi kesalahan jaringan saat mengambil data tiket.';
        } finally {
            this.lookup.loading = false;
        }
    },

    async generateManualIncident() {
        if (!this.form.program_type || this.manualGen.loading) return;

        this.manualGen.loading = true;
        this.manualGen.message = '';

        try {
            const res = await fetch(`/lop/manual-incident?program_type=${encodeURIComponent(this.form.program_type)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();

            if (!res.ok || !json.ok) {
                this.manualGen.message = json.message || 'Gagal membuat nomor tiket manual.';
                return;
            }

            this.form.incident = json.incident;
            this.form.ticket_summary = '';
            this.applyDatek(null);
            this.lookup.notFound = false;
            this.lookup.error = false;
            this.lookup.warnings = [];
            this.lookup.duplicate = null;
            this.lookup.lastQuery = json.incident;
            this.lookup.message = '';
            this.lookup.mode = 'manual';
            this.lookup.summaryTitle = 'Tiket tidak ditemukan — nomor manual dibuat';
            this.lookup.summaryText = `${json.incident} · lengkapi STO, Branch, dan Segmen secara manual.`;
            this.regenerateName();
        } catch (e) {
            this.manualGen.message = 'Terjadi kesalahan jaringan saat membuat nomor manual.';
        } finally {
            this.manualGen.loading = false;
        }
    },

    resetLookupSummary() {
        this.lookup.mode = null;
        this.lookup.summaryTitle = '';
        this.lookup.summaryText = '';
    },

    hydrateDatekText() {
        const d = this.form.datek;
        this.datekText.odc = (d.odc || []).join('\n');
        this.datekText.odp = (d.odp || []).join('\n');
        this.datekText.kabel = (d.kabel || []).join('\n');
        this.datekText.gpon = (d.gpon || [])
            .map((g) => [g.name || '', g.ip || '', (g.ports || []).join(',')].join(' | '))
            .join('\n');
    },

    linesToList(text) {
        return String(text || '').split('\n').map((l) => l.trim()).filter(Boolean);
    },

    datekForSubmit() {
        const d = this.form.datek;
        const gpon = this.linesToList(this.datekText.gpon).map((line) => {
            const [name = '', ip = '', ports = ''] = line.split('|').map((s) => s.trim());
            return { name, ip, ports: ports.split(',').map((p) => p.trim()).filter(Boolean) };
        }).filter((g) => g.name || g.ip || g.ports.length);

        return {
            kategori: d.kategori || '',
            odc: this.linesToList(this.datekText.odc),
            odp: this.linesToList(this.datekText.odp),
            gpon,
            kabel: this.linesToList(this.datekText.kabel),
            ip: Array.isArray(d.ip) ? d.ip : [],
            olt: Boolean(d.olt),
            rca: d.rca || '',
            est: d.est || '',
            pic: { nama: d.pic?.nama || '', telp: d.pic?.telp || '' },
        };
    },

    datekKosong() {
        return !this.datekText.odc.trim()
            && !this.datekText.odp.trim()
            && !this.datekText.gpon.trim()
            && !this.datekText.kabel.trim();
    },

    applyDatek(raw) {
        this.form.datek = normalizeDatek(raw);
        this.hydrateDatekText();
    },

    async parseDatekUlang() {
        if (this.datekParsing) return;
        this.datekParsing = true;
        try {
            const res = await fetch(`/lop/parse-datek?summary=${encodeURIComponent(this.form.ticket_summary || '')}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) this.applyDatek(await res.json());
        } catch (e) {
            // diamkan - field tetap dapat diisi manual
        } finally {
            this.datekParsing = false;
        }
    },

    segmentLabel(value) {
        return this.segmentLabels[value] ?? String(value ?? '').toUpperCase();
    },

    markNameEdited() { this.nameManuallyEdited = true; },

    token(value, uppercase = false) {
        const normalized = String(value ?? '').trim()
            .replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_+|_+$/g, '');
        return uppercase ? normalized.toUpperCase() : normalized;
    },

    programLabel(value) {
        return { recovery: 'QE Recovery', preventive: 'QE Preventive', relok_utilitas: 'QE Relok Utilitas' }[value] ?? '';
    },
});

window.Alpine = Alpine;
Alpine.start();
