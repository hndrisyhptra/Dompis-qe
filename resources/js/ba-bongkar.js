export function signaturePad() {
    return {
        strokes: [],
        active: null,
        observer: null,
        init() {
            this.observer = new ResizeObserver(() => this.redraw());
            this.observer.observe(this.$refs.canvas);
        },
        destroy() { this.observer?.disconnect(); },
        point(event) {
            const box = this.$refs.canvas.getBoundingClientRect();
            return [(event.clientX - box.left) / box.width, (event.clientY - box.top) / box.height];
        },
        start(event) {
            if (event.button !== undefined && event.button !== 0) return;
            this.$refs.canvas.setPointerCapture(event.pointerId);
            if (this.$refs.upload) this.$refs.upload.value = "";
            this.active = [this.point(event)];
            this.strokes.push(this.active);
            this.redraw();
        },
        move(event) {
            if (!this.active) return;
            this.active.push(this.point(event));
            this.redraw();
        },
        end() {
            if (!this.active) return;
            this.active = null;
            this.$refs.value.value = this.$refs.canvas.toDataURL("image/png");
        },
        clear() {
            this.strokes = [];
            this.active = null;
            this.$refs.value.value = "";
            this.redraw();
        },
        redraw() {
            const canvas = this.$refs.canvas;
            const width = canvas.clientWidth;
            if (!width) return;
            canvas.width = Math.round(width * 2);
            canvas.height = 360;
            const ctx = canvas.getContext("2d");
            ctx.fillStyle = "white";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = "#111827";
            ctx.lineWidth = 3;
            ctx.lineCap = "round";
            for (const stroke of this.strokes) {
                ctx.beginPath();
                stroke.forEach(([x, y], i) => {
                    if (i === 0) ctx.moveTo(x * canvas.width, y * canvas.height);
                    else ctx.lineTo(x * canvas.width, y * canvas.height);
                });
                ctx.stroke();
            }
            if (this.strokes.length && !this.active) this.$refs.value.value = canvas.toDataURL("image/png");
        },
    };
}

export function baBongkarForm(choice, rows, groups) {
    return {
        choice: choice === null ? "" : ((choice === true || String(choice) === "1") ? "1" : "0"),
        rows,
        groups,
        saving: false,
        add() { this.rows.push({ group: "distribusi", type: "12 Core", length: "", quantity: "", year: "", brand: "", segments: "", condition: "" }); },
        change(row) { row.type = this.groups[row.group].types[0] || ""; },
    };
}
