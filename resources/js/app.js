import './bootstrap';

import Alpine from 'alpinejs';

window.evidenceUploader = (options = {}) => ({
    expanded: options.expanded ?? true,
    files: [],
    preview: null,

    selectFiles(event) {
        this.releasePreviews();
        this.files = Array.from(event.target.files).map((file) => ({
            file,
            name: file.name,
            size: file.size,
            type: file.type,
            isImage: file.type.startsWith('image/'),
            url: URL.createObjectURL(file),
        }));
    },

    removeFile(index) {
        const removed = this.files[index];

        if (removed?.url) {
            URL.revokeObjectURL(removed.url);
        }

        if (this.preview === removed) {
            this.preview = null;
        }

        this.files.splice(index, 1);
        this.syncInput();
    },

    syncInput() {
        const transfer = new DataTransfer();
        this.files.forEach((item) => transfer.items.add(item.file));
        this.$refs.input.files = transfer.files;
    },

    openPreview(item) {
        this.preview = item;
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
        this.files.forEach((item) => {
            if (item.url) URL.revokeObjectURL(item.url);
        });
    },

    formatSize(bytes) {
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
});

window.lopForm = (options = {}) => ({
    form: {
        incident: '', sto: '', branch: '', area: '3', segment: '',
        wbs_type: '', budget_type: '', job_description: '', ihld_id: '', nama_lop: '',
        ...(options.initial ?? {}),
    },
    template: options.template ?? '{area}{sto}_{wbs_code}_{incident}_{description}',
    wbsCodes: options.wbsCodes ?? {},
    nameManuallyEdited: false,

    init() {
        ['incident', 'sto', 'branch', 'area', 'segment', 'wbs_type', 'budget_type', 'job_description']
            .forEach((field) => this.$watch(`form.${field}`, () => this.regenerateName()));
        if (!this.form.nama_lop) this.regenerateName(true);
    },

    regenerateName(force = false) {
        if (this.nameManuallyEdited && !force) return;
        if (this.form.wbs_type !== 'relok_utilitas') this.form.budget_type = '';

        const values = {
            '{area}': this.token(this.form.area, true),
            '{sto}': this.token(this.form.sto, true),
            '{branch}': this.token(this.form.branch, true),
            '{segment}': this.token(this.form.segment, true),
            '{wbs}': this.token(this.wbsLabel(this.form.wbs_type), true),
            '{wbs_code}': this.wbsCodes[this.form.wbs_type] ?? '',
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

    markNameEdited() { this.nameManuallyEdited = true; },

    token(value, uppercase = false) {
        const normalized = String(value ?? '').trim()
            .replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_+|_+$/g, '');
        return uppercase ? normalized.toUpperCase() : normalized;
    },

    wbsLabel(value) {
        return { recovery: 'QE Recovery', preventive: 'QE Preventive', relok_utilitas: 'QE Relok Utilitas' }[value] ?? '';
    },
});

window.Alpine = Alpine;
Alpine.start();
