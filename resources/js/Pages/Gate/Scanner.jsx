import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2, CloudOff, RefreshCw, SwitchCamera } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { signal, unlockAudio } from '@/lib/feedback';
import { qrHash } from '@/lib/hash';
import { jsonHeaders } from '@/lib/http';
import { CameraError, startScanner } from '@/lib/qrScanner';
import { ScanQueue } from '@/lib/scanQueue';

const QR_PREFIX = 'ABS-';
const SAME_CARD_COOLDOWN_MS = 4000;
const RESULT_RESET_MS = 4000;
const RECENT_LIMIT = 6;
const STATUS_INTERVAL_MS = 60_000;
const FACING_KEY = 'absen:gerbang:kamera';

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const clockFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const formatTime = (value) => timeFormatter.format(new Date(value));

const IDLE_RESULT = { kind: 'idle', kicker: 'Siap', title: 'Tempelkan kartu', detail: 'Arahkan QR kartu absensi ke kamera.' };

function readFacing() {
    try {
        return localStorage.getItem(FACING_KEY) === 'environment' ? 'environment' : 'user';
    } catch {
        return 'user';
    }
}

function saveFacing(facing) {
    try {
        localStorage.setItem(FACING_KEY, facing);
    } catch {
        // Tidak bisa menyimpan pilihan kamera: tetap jalan dengan pilihan ini.
    }
}

/**
 * Keadaan gerbang pada saat ini, dari jadwal hari ini dan jam server.
 */
function gatePhase(today, now) {
    if (today.isFinalized) {
        return 'finalized';
    }

    if (!today.isEnforced) {
        return 'open';
    }

    if (!today.isSchoolDay) {
        return 'no-school';
    }

    if (now < new Date(today.opensAt)) {
        return 'before';
    }

    return now < new Date(today.closesAt) ? 'open' : 'after';
}

function classLabel(student) {
    return student.studyIn ? `${student.classroom} · belajar di ${student.studyIn}` : student.classroom;
}

export default function GateScanner({ station, today: initialToday, roster, marks: initialMarks }) {
    const { appName } = usePage().props;
    const [today, setToday] = useState(initialToday);
    const [clockOffset, setClockOffset] = useState(() => new Date(initialToday.serverNow).getTime() - Date.now());
    const [now, setNow] = useState(() => new Date());
    const [started, setStarted] = useState(false);
    const [camera, setCamera] = useState({ state: 'idle', error: null });
    const [facing, setFacing] = useState(readFacing);
    const [result, setResult] = useState(IDLE_RESULT);
    const [recent, setRecent] = useState([]);
    const [queueState, setQueueState] = useState({ pending: 0, sending: false, online: true });

    const videoRef = useRef(null);
    const stopCameraRef = useRef(null);
    const queueRef = useRef(null);
    const marksRef = useRef({ ...initialMarks });
    const lastReadRef = useRef({ text: null, at: 0 });
    const resetTimerRef = useRef(null);
    const clockOffsetRef = useRef(clockOffset);
    clockOffsetRef.current = clockOffset;

    const rosterById = useMemo(() => new Map(roster.map((student) => [student.id, student])), [roster]);
    const rosterByHash = useMemo(() => new Map(roster.filter((student) => student.hash).map((student) => [student.hash, student])), [roster]);

    const serverNow = useCallback(() => new Date(Date.now() + clockOffsetRef.current), []);
    const phase = gatePhase(today, now);
    const scanning = started && phase === 'open';

    const showResult = useCallback((next) => {
        clearTimeout(resetTimerRef.current);
        setResult(next);
        resetTimerRef.current = setTimeout(() => setResult(IDLE_RESULT), RESULT_RESET_MS);
    }, []);

    const pushRecent = useCallback((entry) => {
        setRecent((current) => [{ key: `${Date.now()}-${Math.random()}`, ...entry }, ...current].slice(0, RECENT_LIMIT));
    }, []);

    // Jam di layar dan pergantian buka/tutup gerbang.
    useEffect(() => {
        const timer = setInterval(() => setNow(serverNow()), 1000);

        return () => clearInterval(timer);
    }, [serverNow]);

    // Hasil dari server untuk scan yang sudah terkirim.
    const handleResults = useCallback(
        (results, batch) => {
            for (const outcome of results) {
                const item = batch.find((queued) => queued.ref === outcome.ref);
                const student = rosterById.get(outcome.studentId ?? item?.meta?.studentId);

                if (outcome.result === 'recorded' || outcome.result === 'duplicate') {
                    if (student) {
                        marksRef.current[student.id] = { status: outcome.status ?? 'H', recordedAt: outcome.recordedAt };
                    }

                    // Kartu yang tidak ada di daftar tab: baru sekarang hasilnya diketahui.
                    if (item?.meta?.unknown) {
                        signal('ok');
                        showResult({ kind: 'ok', kicker: 'Hadir', title: student?.name ?? 'Tercatat hadir', detail: student ? classLabel(student) : 'Muat ulang tab untuk memperbarui daftar siswa.' });
                        pushRecent({ name: student?.name ?? 'Kartu baru', time: outcome.recordedAt, label: 'Hadir', kind: 'ok' });
                    }

                    continue;
                }

                if (item?.meta?.studentId) {
                    delete marksRef.current[item.meta.studentId];
                }

                signal('error');
                showResult({ kind: 'error', kicker: 'Tidak tercatat', title: student?.name ?? 'Kartu ditolak', detail: outcome.message });
                pushRecent({ name: student?.name ?? 'Kartu ditolak', time: serverNow().toISOString(), label: 'Ditolak', kind: 'error' });
            }
        },
        [rosterById, showResult, pushRecent, serverNow],
    );

    const handleResultsRef = useRef(handleResults);
    handleResultsRef.current = handleResults;

    // Satu antrean per tab; scan yang belum terkirim tetap tersimpan saat tab dimuat ulang.
    useEffect(() => {
        const queue = new ScanQueue({
            sessionId: `gerbang-${station.id}`,
            endpoint: '/gerbang/scan',
            now: serverNow,
            onResults: (results, batch) => handleResultsRef.current(results, batch),
            onStateChange: setQueueState,
            onSessionExpired: () => window.location.assign('/kode'),
        });
        queueRef.current = queue;

        for (const item of queue.pendingItems()) {
            if (item.meta?.studentId && !marksRef.current[item.meta.studentId]) {
                marksRef.current[item.meta.studentId] = { status: 'H', recordedAt: item.scanned_at, pending: true };
            }
        }

        queue.emitState();
        queue.flush();

        return () => queue.destroy();
    }, [station.id, serverNow]);

    // Lapor ke server tiap menit: jumlah antrean, jadwal terbaru, dan ganti hari.
    useEffect(() => {
        async function checkIn() {
            try {
                const response = await fetch('/gerbang/status', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ pending: queueRef.current?.pendingItems().length ?? 0 }),
                });

                if (response.status === 401) {
                    window.location.assign('/kode');

                    return;
                }

                if (response.status === 419) {
                    window.location.reload();

                    return;
                }

                if (!response.ok) {
                    return;
                }

                const { today: latest } = await response.json();

                // Hari berganti: muat ulang supaya daftar siswa dan absen hari ini baru.
                if (latest.date !== initialToday.date) {
                    window.location.reload();

                    return;
                }

                setClockOffset(new Date(latest.serverNow).getTime() - Date.now());
                setToday(latest);
            } catch {
                // Offline: coba lagi menit berikutnya.
            }
        }

        const timer = setInterval(checkIn, STATUS_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [initialToday.date]);

    const handleRead = useCallback(
        async (text) => {
            const payload = text.trim().toUpperCase();
            const readAt = Date.now();

            if (lastReadRef.current.text === payload && readAt - lastReadRef.current.at < SAME_CARD_COOLDOWN_MS) {
                return;
            }

            lastReadRef.current = { text: payload, at: readAt };

            if (!payload.startsWith(QR_PREFIX)) {
                signal('error');
                showResult({ kind: 'error', kicker: 'Ditolak', title: 'Bukan kartu absensi', detail: 'QR ini bukan kartu absensi siswa sekolah ini.' });

                return;
            }

            const student = rosterByHash.get(await qrHash(payload));

            if (!student) {
                // Kartu baru atau siswa yang belum ada di daftar tab: tanyakan ke server.
                showResult({ kind: 'checking', kicker: 'Memeriksa', title: 'Kartu sedang diperiksa', detail: 'Tunggu sebentar…' });
                queueRef.current.add({ payload, meta: { unknown: true } });

                return;
            }

            const mark = marksRef.current[student.id];

            if (mark) {
                signal('duplicate');
                showResult({ kind: 'duplicate', kicker: 'Sudah absen', title: student.name, detail: `${classLabel(student)} · tercatat pukul ${formatTime(mark.recordedAt)}` });

                return;
            }

            const scannedAt = serverNow().toISOString();
            marksRef.current[student.id] = { status: 'H', recordedAt: scannedAt, pending: true };
            queueRef.current.add({ payload, meta: { studentId: student.id } });
            signal('ok');
            showResult({ kind: 'ok', kicker: 'Hadir', title: student.name, detail: classLabel(student) });
            pushRecent({ name: student.name, time: scannedAt, label: student.classroom, kind: 'ok' });
        },
        [rosterByHash, showResult, pushRecent, serverNow],
    );

    // Pembaca kamera selalu memanggil versi handleRead terbaru.
    const handleReadRef = useRef(handleRead);
    handleReadRef.current = handleRead;

    const startCamera = useCallback(async (nextFacing) => {
        stopCameraRef.current?.();
        stopCameraRef.current = null;
        setCamera({ state: 'starting', error: null });

        try {
            stopCameraRef.current = await startScanner(videoRef.current, (text) => handleReadRef.current(text), nextFacing);
            setCamera({ state: 'on', error: null });
        } catch (error) {
            setCamera({ state: 'error', error: error instanceof CameraError ? error.message : 'Kamera tidak bisa dibuka.' });
        }
    }, []);

    // Kamera menyala sendiri saat gerbang buka dan mati saat gerbang tutup.
    useEffect(() => {
        if (!scanning) {
            stopCameraRef.current?.();
            stopCameraRef.current = null;
            setCamera({ state: 'idle', error: null });

            return undefined;
        }

        startCamera(facing);

        return () => {
            stopCameraRef.current?.();
            stopCameraRef.current = null;
        };
    }, [scanning, facing, startCamera]);

    useEffect(() => () => clearTimeout(resetTimerRef.current), []);

    // Gerbang ditutup (jam masuk lewat, absen diselesaikan, atau bukan hari
    // sekolah): tab keluar sendiri setelah semua scan terkirim. Server lalu
    // menampilkan "Scan sudah ditutup" dan tab tidak bisa login lagi hari ini.
    const leavingRef = useRef(false);
    const isClosed = phase === 'after' || phase === 'finalized' || phase === 'no-school';

    useEffect(() => {
        if (isClosed && !leavingRef.current && queueState.pending === 0 && !queueState.sending) {
            leavingRef.current = true;
            stopCameraRef.current?.();
            window.location.reload();
        }
    }, [isClosed, queueState.pending, queueState.sending]);

    function start() {
        unlockAudio();
        setStarted(true);
    }

    function switchCamera() {
        const next = facing === 'user' ? 'environment' : 'user';
        saveFacing(next);
        setFacing(next);
    }

    const presentCount = Object.keys(marksRef.current).length;

    return (
        <>
            <Head title={`Gerbang · ${station.name}`} />

            <div className="scan-app gate-app">
                <header className="nav scan-nav">
                    <span className="nav-brand">
                        {appName} <span className="scan-nav-sub">/ Gerbang</span>
                    </span>
                    <span className="scan-class">
                        <b>{station.name}</b>
                        <span className="scan-class-date"> · {dayFormatter.format(now)}</span>
                    </span>
                    {!today.isEnforced && <span className="tag tag-warning">Mode demo</span>}
                    <span className="gate-clock">{clockFormatter.format(now)}</span>
                </header>

                {!started && !isClosed ? (
                    <button type="button" className="gate-screen gate-start" onClick={start}>
                        <span className="gate-screen-kicker">{station.name}</span>
                        <span className="gate-screen-title">Ketuk layar untuk memulai</span>
                        <span className="gate-screen-detail">Cukup sekali setelah tab dinyalakan, supaya kamera dan suara bisa dipakai.</span>
                    </button>
                ) : phase !== 'open' ? (
                    <div className="gate-screen">
                        <span className="gate-screen-kicker">Gerbang ditutup</span>
                        <span className="gate-screen-title">
                            {phase === 'before' && `Dibuka pukul ${formatTime(today.opensAt)}`}
                            {phase === 'after' && `Ditutup pukul ${formatTime(today.closesAt)}`}
                            {phase === 'finalized' && 'Absen hari ini sudah selesai'}
                            {phase === 'no-school' && (today.holiday ? `Libur: ${today.holiday}` : 'Hari ini bukan hari sekolah')}
                        </span>
                        <span className="gate-screen-detail">
                            {phase === 'before' && 'Scanner menyala sendiri saat gerbang dibuka.'}
                            {(phase === 'after' || phase === 'finalized') && 'Siswa yang datang terlambat atau lupa kartu melapor ke guru piket.'}
                            {phase === 'no-school' && 'Scanner tidak dibuka hari ini.'}
                        </span>
                        {isClosed &&
                            (queueState.pending > 0 ? (
                                <span className="gate-screen-detail">
                                    Mengirim {queueState.pending} scan terakhir… Biarkan tab tetap tersambung internet; setelah terkirim tab keluar sendiri.
                                </span>
                            ) : (
                                <span className="gate-screen-detail">Tab keluar…</span>
                            ))}
                    </div>
                ) : (
                    <main className="scan-main gate-main">
                        <section className="scan-col">
                            <div className="scan-camera">
                                <video ref={videoRef} className="scan-video" muted playsInline />
                                {camera.state === 'on' && (
                                    <button type="button" className="scan-switch" onClick={switchCamera}>
                                        <SwitchCamera size={18} aria-hidden="true" /> {facing === 'user' ? 'Kamera belakang' : 'Kamera depan'}
                                    </button>
                                )}
                                <div className="scan-frame" aria-hidden="true">
                                    <span />
                                    <span />
                                    <span />
                                    <span />
                                </div>
                                {camera.state !== 'on' && (
                                    <div className="scan-overlay">
                                        {camera.state === 'error' ? (
                                            <>
                                                <p>{camera.error}</p>
                                                <button type="button" className="btn btn-primary" onClick={() => startCamera(facing)}>
                                                    <RefreshCw size={16} aria-hidden="true" /> Coba lagi
                                                </button>
                                            </>
                                        ) : (
                                            <p>Membuka kamera…</p>
                                        )}
                                    </div>
                                )}
                                {camera.state === 'on' && <div className="scan-hint">Arahkan QR kartu ke dalam bingkai</div>}
                            </div>
                        </section>

                        <section className="scan-col">
                            <div className={`scan-result gate-result is-${result.kind}`} role="status" aria-live="assertive">
                                <div className="scan-result-kicker">{result.kicker}</div>
                                <div className="scan-result-title">{result.title}</div>
                                <div className="scan-result-detail">{result.detail}</div>
                            </div>

                            <div className="scan-stats">
                                <div>
                                    <div className="stat-label">Sudah absen</div>
                                    <div className="scan-stat-value">
                                        {presentCount}
                                        <small>/{roster.length}</small>
                                    </div>
                                </div>
                                <div>
                                    <div className="stat-label">Jam masuk</div>
                                    <div className="scan-stat-value">{today.isEnforced ? formatTime(today.closesAt) : '—'}</div>
                                </div>
                                <div>
                                    <div className="stat-label">Pengiriman</div>
                                    <div className="scan-sync">
                                        {queueState.pending === 0 ? (
                                            <>
                                                <CheckCircle2 size={16} aria-hidden="true" /> Semua terkirim
                                            </>
                                        ) : queueState.online ? (
                                            <>
                                                <RefreshCw size={16} aria-hidden="true" /> Mengirim {queueState.pending}…
                                            </>
                                        ) : (
                                            <>
                                                <CloudOff size={16} aria-hidden="true" /> {queueState.pending} menunggu sinyal
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {recent.length > 0 && (
                                <div className="scan-recent">
                                    <h6 className="text-muted">Scan terakhir</h6>
                                    {recent.map((entry) => (
                                        <div key={entry.key} className={`scan-recent-row is-${entry.kind}`}>
                                            <span>{entry.name}</span>
                                            <span className="text-muted">{formatTime(entry.time)}</span>
                                            <span className="scan-recent-label">{entry.label}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    </main>
                )}
            </div>
        </>
    );
}
