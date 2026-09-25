import './bootstrap';

import Alpine from 'alpinejs';
import imageCompression from 'browser-image-compression';

const COMPRESS_OPTS = { maxWidthOrHeight: 1920, maxSizeMB: 1, initialQuality: 0.72, useWebWorker: true };
const THUMB_OPTS = { maxWidthOrHeight: 360, maxSizeMB: 0.06, initialQuality: 0.6, fileType: 'image/webp', useWebWorker: true };
const MAX_BROWSER_UPLOAD_BYTES = Math.floor(1.75 * 1024 * 1024);
const MAX_CONCURRENT_EVIDENCE_UPLOADS = 2;

const isImageFile = (file) => file.type.startsWith('image/');

/**
 * Kompres gambar tanpa mengubah nama, MIME type, atau ekstensi file aslinya.
 * Non-gambar (PDF) dikembalikan apa adanya.
 * Bila kompresi gagal (browser tua / gambar rusak), pakai file asli.
 */
async function compressImage(file, opts) {
    if (!isImageFile(file)) return file;
    try {
        const blob = await imageCompression(file, { ...opts, fileType: file.type });
        return new File([blob], file.name, {
            type: file.type,
            lastModified: file.lastModified || Date.now(),
        });
    } catch (_) {
        try {
            // Sejumlah browser mobile tidak dapat memakai Web Worker. Ulangi di main thread.
            const blob = await imageCompression(file, { ...opts, useWebWorker: false, fileType: file.type });
            return new File([blob], file.name, {
                type: file.type,
                lastModified: file.lastModified || Date.now(),
            });
        } catch (_) {
            return file;
        }
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
    get queueSelectedCount() { return this.queue.filter((q) => q.status === 'selected').length; },
    get formReady() { return this.files.length > 0 && this.files.every((item) => !item.compressing && !item.error); },

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
                status: 'selected',
                progress: 0,
                error: '',
                raw: file,
            };
            this.queue.push(item);
        }
    },

    startUploads() {
        this.queue.forEach((item) => {
            if (item.status === 'selected') item.status = 'queued';
        });
        this.pump();
    },

    pump() {
        while (this.running < MAX_CONCURRENT_EVIDENCE_UPLOADS) {
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
            if (isImageFile(file) && file.size > MAX_BROWSER_UPLOAD_BYTES) {
                throw new Error('File masih lebih dari 1,75 MB setelah kompresi. Pilih foto yang lebih kecil.');
            }
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
        if (!['selected', 'queued', 'error'].includes(item.status)) return;
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
            // Selalu upload ke origin yang sedang dibuka. URL absolut dari
            // APP_URL/proxy yang salah skema (http vs https) akan diblokir
            // browser mobile sebagai network error sebelum mencapai Laravel.
            const uploadUrl = new URL(this.endpoint, window.location.origin);
            xhr.open('POST', uploadUrl.pathname + uploadUrl.search);
            xhr.timeout = 120000;
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
                } else if (xhr.status === 413) {
                    reject(new Error('Ukuran foto melebihi batas upload server.'));
                } else if (xhr.status === 419) {
                    reject(new Error('Sesi berakhir. Muat ulang halaman lalu coba kembali.'));
                } else {
                    reject(new Error(`Upload gagal (${xhr.status}). Silakan coba kembali.`));
                }
            };
            xhr.onerror = () => reject(new Error(
                navigator.onLine
                    ? 'Koneksi upload diputus server. Coba ulangi; jika tetap gagal, periksa batas upload Nginx/PHP.'
                    : 'Perangkat sedang offline. Periksa koneksi lalu coba lagi.'
            ));
            xhr.onabort = () => reject(new Error('Upload dibatalkan sebelum selesai.'));
            xhr.ontimeout = () => reject(new Error('Upload melewati batas waktu 2 menit.'));
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
            error: '',
        }));
        // Kompres di belakang layar lalu sinkronkan ke <input> untuk submit native.
        await Promise.all(this.files.map(async (item) => {
            if (!item.isImage) return;
            item.file = await compressImage(item.file, COMPRESS_OPTS);
            item.size = item.file.size;
            item.compressing = false;
            if (item.isImage && item.file.size > MAX_BROWSER_UPLOAD_BYTES) {
                item.error = 'File masih lebih dari 1,75 MB setelah kompresi.';
            }
        }));
        if (this.formReady) this.syncInput();
    },

    removeFile(index) {
        const removed = this.files[index];
        if (removed?.url) URL.revokeObjectURL(removed.url);
        if (this.preview === removed) this.preview = null;
        this.files.splice(index, 1);
        if (!this.files.length) {
            this.$refs.input.value = '';
        } else {
            this.syncInput();
        }
    },

    syncInput() {
        try {
            const transfer = new DataTransfer();
            this.files.forEach((item) => transfer.items.add(item.file));
            this.$refs.input.files = transfer.files;
            return true;
        } catch (_) {
            // Safari lama mempertahankan file asli pada input; jangan kosongkan pilihan pengguna.
            return false;
        }
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
        incident: '', sto: '', branch: '', area: '3', segment: '', segments: [],
        program_type: '', budget_type: '', job_description: '', ticket_summary: '', ihld_id: '', nama_lop: '',
        ...(options.initial ?? {}),
        datek: normalizeDatek(options.initial?.datek),
    },
    datekText: { odc: '', odp: '', gpon: '', kabel: '' },
    datekParsing: false,
    template: options.template ?? '{area}{sto}_{program_code}_{incident}_{segment}',
    programCodes: options.programCodes ?? {},
    segmentLabels: options.segmentLabels ?? {},
    segmentOpen: false,
    segmentQuery: '',
    segmentDropdownPos: { top: 0, left: 0, width: 0 },
    areas: options.areas ?? [],
    branches: options.branches ?? [],
    serviceAreas: options.serviceAreas ?? [],
    perangkatOpen: true,
    jalurOpen: true,
    nameManuallyEdited: false,

    get filteredBranches() {
        if (!this.form.area) return [];
        return this.branches.filter(b => String(b.area_code || '') === String(this.form.area));
    },

    get filteredServiceAreas() {
        if (!this.form.branch) return [];
        return this.serviceAreas.filter(sa => String(sa.branch || '') === String(this.form.branch));
    },
    lookup: {
        loading: false, error: false, notFound: false, message: '', warnings: [], lastQuery: null,
        mode: null, summaryTitle: '', summaryText: '', duplicate: null,
    },
    manualGen: { loading: false, message: '' },

    init() {
        // Normalisasi initial: support segment string lama atau array segments
        const init = options.initial ?? {};
        // Jika initial.segments adalah array string, pakai itu untuk multi
        if (Array.isArray(init.segments) && init.segments.length) {
            this.form.segments = init.segments.map((v) => String(v).toLowerCase().trim()).filter(Boolean);
        } else if (Array.isArray(init.segment) && init.segment.length) {
            this.form.segments = init.segment.map((v) => String(v).toLowerCase().trim()).filter(Boolean);
        } else if (typeof init.segment === 'string' && init.segment) {
            this.form.segments = [init.segment.toLowerCase().trim()];
            this.form.segment = init.segment.toLowerCase().trim();
        } else if (typeof init.segments === 'string' && init.segments) {
            this.form.segments = [init.segments.toLowerCase().trim()];
        }

        // Sync single segment dari multi jika program bukan relok
        if (this.form.segments.length && !this.form.segment) {
            this.form.segment = this.form.segments[0];
        }

        ['incident', 'sto', 'branch', 'area', 'segment', 'segments', 'program_type', 'budget_type', 'job_description']
            .forEach((field) => this.$watch(`form.${field}`, () => this.regenerateName()));
        // watcher khusus segments array deep
        this.$watch('form.segments', () => this.regenerateName(), { deep: true });
        this.$watch('form.area', () => this.onAreaChange());
        this.$watch('form.branch', () => this.onBranchChange());
        this.$watch('segmentOpen', (v) => { if (v) this.$nextTick(() => this.updateSegmentPos()); });
        // update posisi dropdown saat resize/scroll (untuk teleport fixed)
        window.addEventListener('resize', () => { if (this.segmentOpen) this.updateSegmentPos(); });
        window.addEventListener('scroll', () => { if (this.segmentOpen) this.updateSegmentPos(); }, true);
        if (!this.form.nama_lop) this.regenerateName(true);
        this.hydrateDatekText();
    },

    updateSegmentPos() {
        const el = this.$refs.segmentBtn || this.$refs.segmentTrigger;
        if (!el) return;
        const r = el.getBoundingClientRect();
        // clamp lebar minimal 280
        const width = Math.max(r.width, 280);
        // jika dekat bottom viewport, dropdown akan tetap di bawah trigger (fixed), max-h-56 sudah scroll
        this.segmentDropdownPos = { top: r.bottom + 8, left: r.left, width: width };
        // koreksi agar tidak keluar kanan viewport
        const vw = window.innerWidth;
        if (this.segmentDropdownPos.left + this.segmentDropdownPos.width > vw - 12) {
            this.segmentDropdownPos.left = Math.max(12, vw - this.segmentDropdownPos.width - 12);
        }
    },

    regenerateName(force = false) {
        if (this.nameManuallyEdited && !force) return;
        if (this.form.program_type !== 'relok_utilitas') this.form.budget_type = '';

        // Gabungan segmen: untuk relok pakai array segments join _, else single segment
        let segmentToken = '';
        if (this.form.program_type === 'relok_utilitas') {
            const segs = Array.isArray(this.form.segments) ? this.form.segments : [];
            const tokens = [];
            const seen = new Set();
            for (const seg of segs) {
                const t = this.token(seg, true);
                if (!t || seen.has(t)) continue;
                seen.add(t);
                tokens.push(t);
            }
            segmentToken = tokens.join('_');
        } else {
            segmentToken = this.token(this.form.segment, true);
        }

        const values = {
            '{area}': this.token(this.form.area, true),
            '{sto}': this.token(this.form.sto, true),
            '{branch}': this.token(this.form.branch, true),
            '{segment}': segmentToken,
            '{segments}': segmentToken,
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

            if (json.branch) {
                this.form.branch = json.branch;
                const b = this.branches.find(x => x.name === json.branch);
                if (b && b.area_code) this.form.area = b.area_code;
            }
            if (json.sto) {
                if (!json.branch) {
                    const sa = this.serviceAreas.find(x => x.workzone === json.sto);
                    if (sa) {
                        this.form.branch = sa.branch;
                        const b2 = this.branches.find(x => x.name === sa.branch);
                        if (b2 && b2.area_code) this.form.area = b2.area_code;
                    }
                }
                this.form.sto = json.sto;
            }
            if (json.segment) {
                if (this.form.program_type === 'relok_utilitas') {
                    // Jika sudah ada, tambahkan tanpa duplikat (preserve order)
                    const seg = String(json.segment).toLowerCase().trim();
                    if (seg && !this.form.segments.includes(seg) && this.form.segments.length < 3) {
                        this.form.segments.push(seg);
                    }
                    // Sync single juga untuk kompatibilitas
                    if (!this.form.segment) this.form.segment = seg;
                } else {
                    this.form.segment = json.segment;
                }
            }
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

        if (this.form.program_type === 'relok_utilitas' && !this.form.branch) {
            this.manualGen.message = 'Pilih Area → Branch → STO terlebih dahulu sebelum generate INP.';
            return;
        }

        this.manualGen.loading = true;
        this.manualGen.message = '';

        try {
            const params = new URLSearchParams({ program_type: this.form.program_type });
            if (this.form.branch) params.set('branch', this.form.branch);
            if (this.form.sto) params.set('sto', this.form.sto);
            const res = await fetch(`/lop/manual-incident?${params.toString()}`, {
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

    // --- Segment combobox helpers (khusus relok_utilitas multi) ---
    segmentOptions() {
        return Object.entries(this.segmentLabels).map(([value, label]) => ({ value, label }));
    },

    filteredSegments() {
        const q = String(this.segmentQuery ?? '').toLowerCase().trim();
        const opts = this.segmentOptions();
        if (!q) return opts;
        return opts.filter((o) => o.value.toLowerCase().includes(q) || String(o.label).toLowerCase().includes(q));
    },

    isSegmentSelected(value) {
        return Array.isArray(this.form.segments) && this.form.segments.includes(value);
    },

    toggleSegment(value) {
        const val = String(value).toLowerCase().trim();
        if (!val) return;
        const idx = this.form.segments.indexOf(val);
        if (idx >= 0) {
            this.form.segments.splice(idx, 1);
        } else {
            if (this.form.segments.length >= 3) return;
            this.form.segments.push(val);
            // keep single sync for fallback
            if (!this.form.segment) this.form.segment = val;
        }
        this.regenerateName();
    },

    onAreaChange() {
        const ok = this.filteredBranches.some(b => b.name === this.form.branch);
        if (!ok) {
            this.form.branch = '';
            this.form.sto = '';
        }
        this.regenerateName();
    },

    onBranchChange() {
        const ok = this.filteredServiceAreas.some(sa => sa.workzone === this.form.sto);
        if (!ok) {
            this.form.sto = '';
        }
        this.regenerateName();
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

window.lopReportModal = () => ({
    lopId: null,
    lopInfo: {},
    reportType: 'boq', // boq | sisa
    loading: false,
    error: '',
    priced: false,
    packageInfo: null,
    columns: {},
    lines: [],
    grand: { qty: 0, qty_actual: 0, sisa: 0, total_actual: 0, nilai_sisa: 0, not_recapped_count: 0, price_missing_count: 0 },

    get title() {
        return this.reportType === 'boq' ? 'BOQ Actual' : 'Sisa Material';
    },

    get isEmpty() {
        return !this.loading && !this.error && this.lines.length === 0;
    },

    isEmpty() {
        return this.lines.length === 0;
    },

    async openReport(detail) {
        // detail: {id, type: 'boq'|'sisa'}
        const id = detail?.id;
        const type = detail?.type || 'boq';
        if (!id) return;
        this.lopId = id;
        this.reportType = type;
        this.lopInfo = { incident: detail.incident || '', nama_lop: detail.nama || '', branch: detail.branch || '', program: detail.program || '' };
        // jika detail sudah bawa info lop, pakai; else fetch akan isi
        this.$refs.dialog?.showModal();
        document.body.classList.add('overflow-hidden');
        await this.fetchReport();
    },

    closeReport() {
        document.body.classList.remove('overflow-hidden');
        // keep data for next open cache? clear after close
    },

    async switchReport(type) {
        if (type === this.reportType) return;
        this.reportType = type;
        await this.fetchReport();
    },

    async fetchReport() {
        if (!this.lopId) return;
        this.loading = true;
        this.error = '';
        try {
            const url = `/reports/lop/${this.lopId}/${this.reportType === 'boq' ? 'boq-actual' : 'sisa-material'}?json=1`;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                const txt = await res.text();
                let msg = `Gagal (${res.status})`;
                try { const j = JSON.parse(txt); msg = j.message || msg; } catch (_) {}
                throw new Error(msg);
            }
            const json = await res.json();
            // json: {report, lop, priced, package, columns, groups, grand}
            this.priced = !!json.priced;
            this.packageInfo = json.package || null;
            this.columns = json.columns || {};
            // groups: perLop single group {lines, subtotal, lop}
            const groups = Array.isArray(json.groups) ? json.groups : [];
            const first = groups[0];
            this.lines = first?.lines || [];
            this.grand = json.grand || this.grand;
            // update lopInfo dari json jika kosong
            if (json.lop) {
                this.lopInfo = { incident: json.lop.incident || this.lopInfo.incident, nama_lop: json.lop.nama_lop || this.lopInfo.nama_lop, branch: json.lop.branch || this.lopInfo.branch, program: json.lop.program || this.lopInfo.program };
            }
        } catch (e) {
            this.error = e?.message || 'Gagal memuat laporan';
            this.lines = [];
        } finally {
            this.loading = false;
        }
    },

    retry() { this.fetchReport(); },

    exportUrl(format) {
        if (!this.lopId) return '#';
        const base = `/reports/lop/${this.lopId}/${this.reportType === 'boq' ? 'boq-actual' : 'sisa-material'}/export`;
        return `${base}?format=${format}`;
    },

    doPrint() {
        // cetak isi modal saja
        const dlg = this.$refs.dialog;
        if (!dlg) return;
        const content = dlg.querySelector('.overflow-y-auto')?.innerHTML || dlg.innerHTML;
        const w = window.open('', '_blank');
        if (!w) return;
        w.document.write(`<html><head><title>${this.title} - ${this.lopInfo.incident || ''}</title><style>body{font-family:Inter,system-ui,sans-serif;padding:24px} table{width:100%;border-collapse:collapse} th,td{border:1px solid #e5e7eb;padding:8px;font-size:12px} th{background:#f9fafb}</style></head><body><h1>${this.title}</h1><p>${this.lopInfo.incident || ''} · ${this.lopInfo.nama_lop || ''}</p>${content}</body></html>`);
        w.document.close();
        w.focus();
        w.print();
    },

    formatNumber(v) {
        if (v == null || v === '') return '—';
        const n = Number(v);
        if (Number.isNaN(n)) return String(v);
        return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    },

    formatMoney(v) {
        if (v == null || v === '') return '—';
        const n = Number(v);
        if (Number.isNaN(n)) return String(v);
        return 'Rp ' + n.toLocaleString('id-ID');
    },
});

// Detail LOP — tab Material & Laporan tanpa scroll horizontal, view only (dipakai di lop-detail-modal)
window.detailLaporanTab = (lopId) => ({
    tab: 'overview',
    type: 'boq',
    loading: false,
    error: '',
    priced: false,
    packageInfo: null,
    lines: [],
    grand: { qty: 0, qty_actual: 0, sisa: 0, total_actual: 0, nilai_sisa: 0, not_recapped_count: 0, price_missing_count: 0 },
    _fetched: { boq: false, sisa: false },

    async fetchIfNeeded(t) {
        const key = t || this.type;
        if (this._fetched[key]) return;
        await this.fetchReport(key);
    },

    async switchType(t) {
        this.type = t;
        await this.fetchIfNeeded(t);
    },

    async fetchReport(t) {
        const type = t || this.type;
        this.loading = true;
        this.error = '';
        try {
            const url = `/reports/lop/${lopId}/${type === 'boq' ? 'boq-actual' : 'sisa-material'}?json=1`;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                const txt = await res.text();
                let msg = `Gagal (${res.status})`;
                try { const j = JSON.parse(txt); msg = j.message || msg; } catch (_) {}
                throw new Error(msg);
            }
            const json = await res.json();
            this.priced = !!json.priced;
            this.packageInfo = json.package || null;
            const groups = Array.isArray(json.groups) ? json.groups : [];
            this.lines = groups[0]?.lines || [];
            this.grand = json.grand || this.grand;
            this._fetched[type] = true;
        } catch (e) {
            this.error = e?.message || 'Gagal memuat laporan';
            this.lines = [];
        } finally {
            this.loading = false;
        }
    },

    retry() { this._fetched[this.type] = false; this.fetchReport(this.type); },

    formatNumber(v) {
        if (v == null || v === '') return '—';
        const n = Number(v);
        if (Number.isNaN(n)) return String(v);
        return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    },

    formatMoney(v) {
        if (v == null || v === '') return '—';
        const n = Number(v);
        if (Number.isNaN(n)) return String(v);
        return 'Rp ' + n.toLocaleString('id-ID');
    },
});

window.boqEditor = (encodedItems, encodedOptions) => {
    const initialItems = JSON.parse(atob(encodedItems));
    const sharedOptions = encodedOptions
        || document.querySelector('[data-boq-options]')?.dataset.boqOptions
        || btoa('[]');
    const withSearch = (items) => items.map((item) => ({ ...item, search: '', open: false }));

    return {
        detail: false,
        edit: false,
        remove: false,
        saveConfirm: false,
        dropConfirm: false,
        pendingDropIndex: null,
        originalItems: initialItems,
        items: withSearch(JSON.parse(JSON.stringify(initialItems))),
        // Daftar master designator disisipkan sekali per halaman, bukan sekali
        // untuk setiap baris BOQ. Ini menjaga HTML tetap kecil saat pagination penuh.
        options: JSON.parse(atob(sharedOptions)),

        openEdit() {
            this.items = withSearch(JSON.parse(JSON.stringify(this.originalItems)));
            this.edit = true;
        },

        closeEdit() {
            this.edit = false;
            this.saveConfirm = false;
            this.dropConfirm = false;
            this.pendingDropIndex = null;
        },

        add() {
            this.items.forEach((item) => { item.open = false; });
            this.items.push({ designator_id: '', qty: 1, unit_price: 0, search: '', open: true });
        },

        filteredOptions(search, selectedId) {
            const term = String(search || '').trim().toLocaleLowerCase('id-ID');
            const selected = this.options.find((option) => String(option.id) === String(selectedId));
            let matches = [];

            if (term) {
                matches = this.options.filter((option) => [option.code, option.name, option.unit, option.type]
                    .filter(Boolean)
                    .some((value) => String(value).toLocaleLowerCase('id-ID').includes(term)));
            } else {
                matches = this.options.slice(0, 30);
            }

            if (selected) {
                matches = [selected, ...matches.filter((option) => String(option.id) !== String(selected.id))];
            }

            return matches.slice(0, 60);
        },

        selectedCode(selectedId) {
            const selected = this.options.find((option) => String(option.id) === String(selectedId));

            return selected ? `${selected.code} · ${selected.name}` : 'Belum memilih designator';
        },

        selectedOptionLabel(selectedId) {
            const selected = this.options.find((option) => String(option.id) === String(selectedId));

            return selected ? `${selected.code} · ${selected.name}` : 'Pilih designator';
        },

        toggleOptions(targetItem) {
            const willOpen = !targetItem.open;
            this.items.forEach((item) => {
                item.open = false;
                item.search = '';
            });
            targetItem.open = willOpen;
        },

        selectOption(item, option) {
            item.designator_id = String(option.id);
            item.search = '';
            item.open = false;
        },

        optionUsedByOther(optionId, currentIndex) {
            return this.items.some((item, index) => index !== currentIndex && String(item.designator_id) === String(optionId));
        },

        searchHint(search, selectedId) {
            const term = String(search || '').trim();
            const resultCount = this.filteredOptions(term, selectedId).length;

            if (term) {
                return resultCount
                    ? `${resultCount} hasil ditemukan.`
                    : 'Tidak ada hasil. Coba kata kunci lain.';
            }

            return 'Menampilkan 30 designator awal. Ketik kata kunci untuk hasil yang lebih spesifik.';
        },

        requestDrop(index) {
            if (this.items.length <= 1) return;
            this.pendingDropIndex = index;
            this.dropConfirm = true;
        },

        confirmDrop() {
            if (this.pendingDropIndex !== null && this.items.length > 1) {
                this.items.splice(this.pendingDropIndex, 1);
            }
            this.pendingDropIndex = null;
            this.dropConfirm = false;
        },
    };
};

window.importUploadForm = (action) => ({
    action,
    fileName: '',
    uploading: false,
    uploadPercent: 0,
    phase: 'Pilih file untuk memulai',
    error: '',

    selectFile(event) {
        this.fileName = event.target.files?.[0]?.name || '';
        this.error = '';
        this.uploadPercent = 0;
        this.phase = this.fileName ? 'File siap diunggah' : 'Pilih file untuk memulai';
    },

    submit(event) {
        if (this.uploading) return;

        const form = event.currentTarget;
        if (!form.reportValidity()) return;

        this.uploading = true;
        this.uploadPercent = 0;
        this.error = '';
        this.phase = 'Mengunggah file';

        const xhr = new XMLHttpRequest();
        const uploadUrl = new URL(this.action, window.location.origin);
        xhr.open('POST', uploadUrl.pathname + uploadUrl.search);
        xhr.timeout = 180000;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = (progress) => {
            if (progress.lengthComputable) {
                this.uploadPercent = Math.round((progress.loaded / progress.total) * 100);
            }
        };
        xhr.onload = () => {
            let payload = {};
            try { payload = JSON.parse(xhr.responseText); } catch (_) {}

            if (xhr.status >= 200 && xhr.status < 300 && payload.result_url) {
                this.uploadPercent = 100;
                this.phase = 'Upload selesai · membuka progres antrean';
                window.location.assign(payload.result_url);
                return;
            }

            const firstError = Object.values(payload.errors || {})[0]?.[0];
            this.error = firstError || payload.message || `Upload gagal (${xhr.status}).`;
            this.uploading = false;
            this.phase = 'Upload belum berhasil';
        };
        xhr.onerror = () => {
            this.error = 'Upload tidak mencapai server. Periksa koneksi, konfigurasi HTTPS/domain, serta batas upload web server.';
            this.uploading = false;
            this.phase = 'Upload belum berhasil';
        };
        xhr.ontimeout = () => {
            this.error = 'Upload melewati batas waktu. Periksa koneksi lalu coba kembali.';
            this.uploading = false;
            this.phase = 'Upload belum berhasil';
        };
        xhr.send(new FormData(form));
    },
});

window.importBatchProgress = (options = {}) => ({
    statusUrl: options.statusUrl,
    status: options.status || 'queued',
    total: Number(options.total || 0),
    success: Number(options.success || 0),
    failed: Number(options.failed || 0),
    processed: Number(options.processed || 0),
    percentage: Number(options.percentage || 0),
    finished: Boolean(options.finished),
    reloadOnFinish: Boolean(options.reloadOnFinish),
    pollTimer: null,
    pollError: false,

    get statusLabel() {
        return ({ queued: 'Menunggu antrean', processing: 'Sedang diproses', completed: 'Selesai', partial: 'Selesai sebagian', failed: 'Gagal' })[this.status] || this.status;
    },

    start() {
        if (!this.finished) this.schedule(250);
    },

    schedule(delay = 1800) {
        clearTimeout(this.pollTimer);
        this.pollTimer = setTimeout(() => this.refresh(), delay);
    },

    async refresh() {
        try {
            const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Status ${response.status}`);
            const data = await response.json();
            const wasFinished = this.finished;
            this.status = data.status;
            this.total = Number(data.total_rows || 0);
            this.success = Number(data.success_rows || 0);
            this.failed = Number(data.failed_rows || 0);
            this.processed = Number(data.processed_rows || 0);
            this.percentage = Number(data.percentage || 0);
            this.finished = Boolean(data.finished);
            this.pollError = false;

            if (this.finished && !wasFinished && this.reloadOnFinish) {
                window.location.reload();
                return;
            }
        } catch (_) {
            this.pollError = true;
        }

        if (!this.finished) this.schedule(this.pollError ? 4000 : 1800);
    },
});

window.dashboardMatrixModal = (endpoint) => ({
    endpoint,
    title: 'Daftar LOP',
    subtitle: '',
    loading: false,
    error: '',
    rows: [],
    total: 0,

    async open(title, filters = {}) {
        this.title = title;
        this.subtitle = 'Memuat data sesuai angka pada matrix';
        this.loading = true;
        this.error = '';
        this.rows = [];
        this.total = 0;
        this.$refs.matrixModal.showModal();

        const url = new URL(this.endpoint, window.location.origin);
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '' && value != null) url.searchParams.set(key, value);
        });

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Gagal memuat data (${response.status})`);
            const data = await response.json();
            this.rows = data.data || [];
            this.total = Number(data.total || 0);
            this.subtitle = `${this.total.toLocaleString('id-ID')} LOP ditemukan`;
        } catch (error) {
            this.error = error?.message || 'Daftar LOP tidak dapat dimuat.';
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.$refs.matrixModal.close();
    },
});

window.Alpine = Alpine;
Alpine.start();
