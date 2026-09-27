import { jsonHeaders } from '@/lib/http';

/**
 * Antrean hasil scan di HP. Setiap scan langsung disimpan di localStorage,
 * lalu dikirim ke server berkelompok. Kalau sinyal putus, antrean tetap
 * tersimpan (juga saat halaman dimuat ulang) dan dikirim lagi otomatis.
 */

const RETRY_MS = 3000;
const BATCH_SIZE = 50;

function newRef() {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export class ScanQueue {
    constructor({ sessionId, endpoint, onResults, onStateChange, onSessionExpired }) {
        this.storageKey = `absen:antrean:${sessionId}`;
        this.endpoint = endpoint;
        this.onResults = onResults;
        this.onStateChange = onStateChange;
        this.onSessionExpired = onSessionExpired;
        this.items = this.load();
        this.sending = false;
        this.online = navigator.onLine;
        this.timer = setInterval(() => this.flush(), RETRY_MS);
        this.handleOnline = () => {
            this.online = true;
            this.flush();
        };
        window.addEventListener('online', this.handleOnline);
    }

    load() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKey) ?? '[]');
        } catch {
            return [];
        }
    }

    save() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.items));
        } catch {
            // Penyimpanan penuh/diblokir: antrean tetap ada di memori.
        }

        this.emitState();
    }

    emitState() {
        this.onStateChange?.({ pending: this.items.length, sending: this.sending, online: this.online });
    }

    /**
     * @param {{ method: 'scan'|'manual', payload?: string, student_id?: number, meta?: object }} item
     * @returns {string} ref untuk mencocokkan hasil dari server
     */
    add(item) {
        const ref = newRef();
        this.items.push({ ...item, ref, scanned_at: new Date().toISOString() });
        this.save();
        this.flush();

        return ref;
    }

    async flush() {
        if (this.sending || this.items.length === 0) {
            return;
        }

        this.sending = true;
        this.emitState();
        const batch = this.items.slice(0, BATCH_SIZE);

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: jsonHeaders(),
                body: JSON.stringify({ scans: batch.map(({ meta, ...scan }) => scan) }),
            });

            if (response.status === 401) {
                this.onSessionExpired?.();

                return;
            }

            if (response.status === 419) {
                // Token keamanan kedaluwarsa: muat ulang, antrean tetap tersimpan.
                window.location.reload();

                return;
            }

            const refs = new Set(batch.map((item) => item.ref));
            let results;

            if (response.ok) {
                results = (await response.json()).results;
            } else if (response.status === 422) {
                results = batch.map((item) => ({ ref: item.ref, result: 'rejected', message: 'Data scan tidak valid.' }));
            } else {
                throw new Error(`HTTP ${response.status}`);
            }

            this.online = true;
            this.items = this.items.filter((item) => !refs.has(item.ref));
            this.save();
            this.onResults?.(results, batch);
        } catch {
            this.online = false;
        } finally {
            this.sending = false;
            this.emitState();
        }

        if (this.items.length > 0 && this.online) {
            this.flush();
        }
    }

    pendingItems() {
        return [...this.items];
    }

    destroy() {
        clearInterval(this.timer);
        window.removeEventListener('online', this.handleOnline);
    }
}
