import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { getJson } from '@/lib/http';

/** Seberapa sering halaman menanyakan apakah ada data baru. */
export const LIVE_UPDATE_INTERVAL = 3000;

/**
 * Pagi hari absen berubah terus karena scan di gerbang. Supaya server
 * hosting tidak kewalahan, halaman dimuat ulang karena absen paling sering
 * sekali per 10 detik. Perubahan lain (dispensasi, izin, …) tetap langsung.
 */
const ATTENDANCE_RELOAD_GAP = 10000;

/**
 * Perbarui halaman yang sedang terbuka bila pengguna lain mengubah data
 * (dispensasi, izin, scan terlambat, …). Setiap 3 detik, dan setiap kali
 * tab kembali dibuka, versi data ditanyakan ke server; bila berubah, data
 * halaman dimuat ulang diam-diam tanpa menghapus isian form atau posisi gulir.
 */
export function useLiveUpdates({ onNewPendingDispensations } = {}) {
    const versions = useRef(null);
    const pending = useRef(null);
    const attendanceWaiting = useRef(false);
    const lastReload = useRef(0);
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
            lastReload.current = Date.now();
        });

        function reload() {
            attendanceWaiting.current = false;
            lastReload.current = Date.now();
            router.reload({ preserveScroll: true, preserveState: true });
        }

        async function check() {
            if (document.visibilityState !== 'visible' || busy.current || visiting.current) {
                return;
            }

            busy.current = true;

            try {
                const known = versions.current;
                const data = await getJson(`/pembaruan${known ? `?${new URLSearchParams({ data: known.data })}` : ''}`);

                if (cancelled) {
                    return;
                }

                const latest = data.versions;

                if (known !== null && latest.attendance !== known.attendance) {
                    attendanceWaiting.current = true;
                }

                versions.current = latest;

                if (data.counts) {
                    const pendingNow = data.counts.pendingDispensations ?? 0;

                    if (pending.current !== null && pendingNow > pending.current) {
                        callback.current?.(pendingNow - pending.current);
                    }

                    pending.current = pendingNow;
                }

                if (visiting.current) {
                    return;
                }

                if (known !== null && latest.data !== known.data) {
                    reload();
                } else if (attendanceWaiting.current && Date.now() - lastReload.current >= ATTENDANCE_RELOAD_GAP) {
                    reload();
                }
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
