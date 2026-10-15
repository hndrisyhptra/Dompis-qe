export function copyableValue(value) {
    return {
        value: String(value),
        copied: false,
        copying: false,
        failed: false,
        resetTimer: null,
        async copy() {
            if (this.copying) return;
            this.copying = true;
            this.failed = false;
            this.copied = false;
            clearTimeout(this.resetTimer);
            try {
                await navigator.clipboard.writeText(this.value);
                this.copied = true;
                this.resetTimer = setTimeout(() => { this.copied = false; }, 2000);
            } catch {
                this.failed = true;
            } finally {
                this.copying = false;
            }
        },
        destroy() {
            clearTimeout(this.resetTimer);
        },
    };
}
