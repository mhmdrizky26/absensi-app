import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { getJson } from '@/lib/http';

/** Seberapa sering halaman menanyakan apakah ada data baru. */
export const LIVE_UPDATE_INTERVAL = 12000;

/**
 * Perbarui halaman yang sedang terbuka bila pengguna lain mengubah data
 * (dispensasi, izin, scan terlambat, …). Setiap 12 detik, dan setiap kali
 * tab kembali dibuka, versi data ditanyakan ke server; bila berubah, data
 * halaman dimuat ulang diam-diam tanpa menghapus isian form atau posisi gulir.
 */
export function useLiveUpdates({ onNewPendingDispensations } = {}) {
    const version = useRef(null);
    const pending = useRef(null);
    const busy = useRef(false);
    const visiting = useRef(false);
    const callback = useRef(onNewPendingDispensations);
    callback.current = onNewPendingDispensations;

    useEffect(() => {
        let cancelled = false;
        const stopStart = router.on('start', () => {
            visiting.current = true;
        });
        const stopFinish = router.on('finish', () => {
            visiting.current = false;
        });

        async function check() {
            if (document.visibilityState !== 'visible' || busy.current || visiting.current) {
                return;
            }

            busy.current = true;

            try {
                const data = await getJson('/pembaruan');

                if (cancelled) {
                    return;
                }

                if (version.current !== null && data.version !== version.current && !visiting.current) {
                    router.reload({ preserveScroll: true, preserveState: true });
                }

                const pendingNow = data.counts?.pendingDispensations ?? 0;

                if (pending.current !== null && pendingNow > pending.current) {
                    callback.current?.(pendingNow - pending.current);
                }

                version.current = data.version;
                pending.current = pendingNow;
            } catch {
                // Jaringan putus sebentar: coba lagi pada putaran berikutnya.
            } finally {
                busy.current = false;
            }
        }

        check();
        const timer = setInterval(check, LIVE_UPDATE_INTERVAL);
        const onVisible = () => document.visibilityState === 'visible' && check();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
            stopStart();
            stopFinish();
        };
    }, []);
}
