const numericCoordinate = (value) => {
    const text = String(value ?? "").trim();
    if (!/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/.test(text)) return null;
    const number = Number(text);
    return Number.isFinite(number) ? number : null;
};

export function parseCoordinates(value) {
    const text = String(value ?? "").trim().replace(/^\((.*)\)$/, "$1").trim();
    const parts = text.split(/\s*[,;]\s*|\s+/);
    if (parts.length !== 2) return null;
    let [latitude, longitude] = parts.map(numericCoordinate);
    if (latitude === null || longitude === null) return null;
    // Long-lat dapat dikenali hanya bila angka pertama di luar rentang latitude.
    if (Math.abs(latitude) > 90 && Math.abs(longitude) <= 90) {
        [latitude, longitude] = [longitude, latitude];
    }
    if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) return null;
    return { latitude: latitude.toFixed(7), longitude: longitude.toFixed(7) };
}

export function technicianLocation(options = {}, geolocation = globalThis.navigator?.geolocation) {
    return {
        latitude: String(options.latitude ?? ""),
        longitude: String(options.longitude ?? ""),
        accuracy: String(options.accuracy ?? ""),
        source: options.source === "gps" ? "gps" : "manual",
        coordinates: "",
        coordinateError: "",
        error: "",
        dirty: Boolean(options.dirty),
        locating: false,
        gpsVersion: 0,
        get hasCoordinates() {
            const lat = numericCoordinate(this.latitude);
            const lng = numericCoordinate(this.longitude);
            return lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
        },
        get canSave() {
            return this.hasCoordinates && !this.coordinateError && !this.locating;
        },
        get mapUrl() {
            return this.hasCoordinates ? `https://maps.google.com/?q=${encodeURIComponent(`${this.latitude},${this.longitude}`)}` : null;
        },
        applyCoordinates() {
            this.gpsVersion++;
            this.locating = false;
            this.error = "";
            this.coordinateError = "";
            if (!this.coordinates.trim()) return;
            this.dirty = true;
            const parsed = parseCoordinates(this.coordinates);
            if (!parsed) {
                this.coordinateError = "Koordinat belum valid. Contoh: -7.257472, 112.752090. Gunakan titik untuk desimal.";
                return;
            }
            this.latitude = parsed.latitude;
            this.longitude = parsed.longitude;
            this.accuracy = "";
            this.source = "manual";
        },
        manualChanged() {
            this.gpsVersion++;
            this.locating = false;
            this.coordinates = "";
            this.coordinateError = "";
            this.error = "";
            this.accuracy = "";
            this.source = "manual";
            this.dirty = true;
        },
        locate() {
            if (this.locating) return;
            this.error = "";
            if (!geolocation) {
                this.error = "GPS tidak didukung perangkat. Tempel koordinat atau lanjut tanpa lokasi.";
                return;
            }
            const version = ++this.gpsVersion;
            this.locating = true;
            const failed = () => {
                if (version !== this.gpsVersion) return;
                this.locating = false;
                this.error = "Lokasi gagal diambil. Isi manual atau lanjut tanpa lokasi.";
            };
            try {
                geolocation.getCurrentPosition((position) => {
                    if (version !== this.gpsVersion) return;
                    const coords = position.coords;
                    if (!Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)
                        || Math.abs(coords.latitude) > 90 || Math.abs(coords.longitude) > 180) {
                        failed();
                        return;
                    }
                    this.latitude = coords.latitude.toFixed(7);
                    this.longitude = coords.longitude.toFixed(7);
                    this.accuracy = Number.isFinite(coords.accuracy) && coords.accuracy >= 0 ? coords.accuracy.toFixed(2) : "";
                    this.source = "gps";
                    this.coordinates = "";
                    this.coordinateError = "";
                    this.dirty = true;
                    this.locating = false;
                }, failed, { enableHighAccuracy: true, timeout: 15000 });
            } catch {
                failed();
            }
        },
    };
}
