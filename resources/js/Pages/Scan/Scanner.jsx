import { Head, Link, router } from '@inertiajs/react';
import { Camera, CheckCircle2, CloudOff, Lock, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { signal, unlockAudio } from '@/lib/feedback';
import { qrHash } from '@/lib/hash';
import { CameraError, startScanner } from '@/lib/qrScanner';
import { ScanQueue } from '@/lib/scanQueue';

const QR_PREFIX = 'ABS-';
const SAME_CARD_COOLDOWN_MS = 2500;
const RECENT_LIMIT = 8;

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });

const formatTime = (iso) => timeFormatter.format(new Date(iso));

const isAtSchool = (mark) => mark && ['H', 'T', 'D'].includes(mark.status);

const IDLE_RESULT = { kind: 'idle', kicker: 'Siap', title: 'Scan kartu siswa', detail: 'Hasil scan muncul di sini.' };

export default function Scanner({ classroom, session, roster, marks: initialMarks }) {
    const [marks, setMarks] = useState(() => ({ ...initialMarks }));
    const [result, setResult] = useState(IDLE_RESULT);
    const [recent, setRecent] = useState([]);
    const [queueState, setQueueState] = useState({ pending: 0, sending: false, online: true });
    const [camera, setCamera] = useState({ state: 'idle', error: null });
    const [tab, setTab] = useState('missing');
    const [closing, setClosing] = useState(false);
    const [closeError, setCloseError] = useState(null);

    const videoRef = useRef(null);
    const stopCameraRef = useRef(null);
    const queueRef = useRef(null);
    const marksRef = useRef(marks);
    const lastReadRef = useRef({ text: null, at: 0 });
    marksRef.current = marks;

    const rosterById = useMemo(() => new Map(roster.map((student) => [student.id, student])), [roster]);
    const rosterByHash = useMemo(() => new Map(roster.filter((student) => student.hash).map((student) => [student.hash, student])), [roster]);

    const pushRecent = useCallback((entry) => {
        setRecent((current) => [{ key: `${Date.now()}-${Math.random()}`, ...entry }, ...current].slice(0, RECENT_LIMIT));
    }, []);

    const setMark = useCallback((studentId, mark) => {
        setMarks((current) => {
            const next = { ...current };

            if (mark) {
                next[studentId] = mark;
            } else {
                delete next[studentId];
            }

            return next;
        });
    }, []);

    // Hasil dari server untuk scan yang sudah terkirim.
    const handleResults = useCallback(
        (results, batch) => {
            for (const outcome of results) {
                const item = batch.find((queued) => queued.ref === outcome.ref);
                const student = rosterById.get(outcome.studentId ?? item?.meta?.studentId);

                if (outcome.result === 'recorded' || outcome.result === 'duplicate') {
                    if (!student) {
                        router.reload({ only: ['roster', 'marks'] });
                        continue;
                    }

                    setMark(student.id, { status: 'H', statusLabel: 'Hadir', recordedAt: outcome.recordedAt, pending: false });

                    if (item?.meta?.unknown) {
                        signal('ok');
                        setResult({ kind: 'ok', kicker: 'Hadir', title: student.name, detail: `NIS ${student.nis} · ${formatTime(outcome.recordedAt)}` });
                        pushRecent({ name: student.name, time: outcome.recordedAt, label: 'Hadir', kind: 'ok' });
                    }

                    continue;
                }

                // Ditolak server: batalkan tanda sementara di HP.
                if (item?.meta?.studentId && marksRef.current[item.meta.studentId]?.pending) {
                    setMark(item.meta.studentId, null);
                }

                signal('error');
                setResult({ kind: 'error', kicker: 'Ditolak', title: student?.name ?? 'Kartu tidak diterima', detail: outcome.message });
                pushRecent({ name: student?.name ?? 'Kartu ditolak', time: new Date().toISOString(), label: 'Ditolak', kind: 'error' });
            }
        },
        [rosterById, setMark, pushRecent],
    );

    const handleResultsRef = useRef(handleResults);
    handleResultsRef.current = handleResults;

    // Satu antrean per sesi; hasilnya selalu diteruskan ke handler terbaru.
    useEffect(() => {
        const queue = new ScanQueue({
            sessionId: session.id,
            endpoint: '/absen/kelas/scan',
            onResults: (results, batch) => handleResultsRef.current(results, batch),
            onStateChange: setQueueState,
            onSessionExpired: () => window.location.assign('/absen'),
        });
        queueRef.current = queue;

        // Scan yang belum terkirim dari kunjungan sebelumnya tetap ditampilkan.
        for (const item of queue.pendingItems()) {
            if (item.meta?.studentId && !isAtSchool(marksRef.current[item.meta.studentId])) {
                setMark(item.meta.studentId, { status: 'H', statusLabel: 'Hadir', recordedAt: item.scanned_at, pending: true });
            }
        }

        queue.emitState();
        queue.flush();

        return () => queue.destroy();
    }, [session.id, setMark]);

    const markPresent = useCallback(
        (student, method, payload) => {
            const now = new Date().toISOString();
            setMark(student.id, { status: 'H', statusLabel: 'Hadir', recordedAt: now, pending: true });
            queueRef.current.add(method === 'scan' ? { method, payload, meta: { studentId: student.id } } : { method, student_id: student.id, meta: { studentId: student.id } });
            signal('ok');
            setResult({ kind: 'ok', kicker: method === 'scan' ? 'Hadir' : 'Hadir · manual', title: student.name, detail: `NIS ${student.nis} · ${formatTime(now)}` });
            pushRecent({ name: student.name, time: now, label: method === 'scan' ? 'Hadir' : 'Manual', kind: 'ok' });
        },
        [setMark, pushRecent],
    );

    const handleRead = useCallback(
        async (text) => {
            const payload = text.trim().toUpperCase();
            const now = Date.now();

            if (lastReadRef.current.text === payload && now - lastReadRef.current.at < SAME_CARD_COOLDOWN_MS) {
                return;
            }

            lastReadRef.current = { text: payload, at: now };

            if (!payload.startsWith(QR_PREFIX)) {
                signal('error');
                setResult({ kind: 'error', kicker: 'Ditolak', title: 'Bukan kartu absensi', detail: 'QR ini bukan kartu absensi siswa sekolah ini.' });

                return;
            }

            const student = rosterByHash.get(await qrHash(payload));

            if (!student) {
                // Mungkin siswa kelas lain atau kartu lama: tanyakan ke server.
                setResult({ kind: 'checking', kicker: 'Memeriksa', title: 'Kartu tidak ada di daftar kelas ini', detail: 'Menanyakan ke server…' });
                queueRef.current.add({ method: 'scan', payload, meta: { unknown: true } });

                return;
            }

            const mark = marksRef.current[student.id];

            if (isAtSchool(mark)) {
                signal('duplicate');
                setResult({ kind: 'duplicate', kicker: 'Sudah absen', title: student.name, detail: `Tercatat pukul ${formatTime(mark.recordedAt)}` });

                return;
            }

            markPresent(student, 'scan', payload);
        },
        [rosterByHash, markPresent],
    );

    // Pembaca kamera selalu memanggil versi handleRead terbaru.
    const handleReadRef = useRef(handleRead);
    handleReadRef.current = handleRead;

    async function startCamera() {
        unlockAudio();
        setCamera({ state: 'starting', error: null });

        try {
            stopCameraRef.current = await startScanner(videoRef.current, (text) => handleReadRef.current(text));
            setCamera({ state: 'on', error: null });
        } catch (error) {
            setCamera({ state: 'error', error: error instanceof CameraError ? error.message : 'Kamera tidak bisa dibuka.' });
        }
    }

    useEffect(() => () => stopCameraRef.current?.(), []);

    async function finish() {
        setCloseError(null);
        await queueRef.current.flush();

        if (queueRef.current.pendingItems().length > 0) {
            setCloseError(`Masih ada ${queueRef.current.pendingItems().length} scan yang belum terkirim. Pastikan internet tersambung, lalu coba lagi.`);

            return;
        }

        stopCameraRef.current?.();
        router.post('/absen/kelas/selesai', {}, { onFinish: () => setClosing(false) });
    }

    const present = roster.filter((student) => isAtSchool(marks[student.id]));
    const missing = roster.filter((student) => !isAtSchool(marks[student.id]));
    const excused = missing.filter((student) => marks[student.id]);

    return (
        <>
            <Head title={`Scan ${classroom.name}`} />

            <div className="scan-app">
                <header className="nav scan-nav">
                    <span className="nav-brand">
                        Absensi <span className="scan-nav-sub">/ Scan kelas</span>
                    </span>
                    <span className="scan-class">
                        <b>Kelas {classroom.name}</b>
                        <span className="scan-class-date"> · {dayFormatter.format(new Date(`${session.date}T00:00:00`))}</span>
                    </span>
                    <Link href="/absen/keluar" method="post" as="button" className="btn btn-secondary" aria-label="Kunci scanner">
                        <Lock size={16} aria-hidden="true" /> <span className="nav-logout-label">Kunci</span>
                    </Link>
                </header>

                <main className="scan-main">
                    <section className="scan-col">
                        <div className="scan-camera">
                            <video ref={videoRef} className="scan-video" muted playsInline />
                            <div className="scan-frame" aria-hidden="true">
                                <span />
                                <span />
                                <span />
                                <span />
                                {camera.state === 'on' && <i className="scan-line" />}
                            </div>
                            {camera.state !== 'on' && (
                                <div className="scan-overlay">
                                    {camera.state === 'error' ? (
                                        <>
                                            <p>{camera.error}</p>
                                            <button type="button" className="btn btn-primary" onClick={startCamera}>
                                                <RefreshCw size={16} aria-hidden="true" /> Coba lagi
                                            </button>
                                        </>
                                    ) : (
                                        <button type="button" className="btn btn-primary scan-start" onClick={startCamera} disabled={camera.state === 'starting'}>
                                            <Camera size={20} aria-hidden="true" /> {camera.state === 'starting' ? 'Membuka kamera…' : 'Mulai scan'}
                                        </button>
                                    )}
                                </div>
                            )}
                            {camera.state === 'on' && <div className="scan-hint">Arahkan kartu QR ke dalam bingkai</div>}
                        </div>

                        <div className={`scan-result is-${result.kind}`} role="status" aria-live="assertive">
                            <div className="scan-result-kicker">{result.kicker}</div>
                            <div className="scan-result-title">{result.title}</div>
                            <div className="scan-result-detail">{result.detail}</div>
                        </div>

                        <div className="scan-stats">
                            <div>
                                <div className="stat-label">Hadir</div>
                                <div className="scan-stat-value">
                                    {present.length}
                                    <small>/{roster.length}</small>
                                </div>
                            </div>
                            <div>
                                <div className="stat-label">Belum absen</div>
                                <div className="scan-stat-value">{missing.length}</div>
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

                    <section className="scan-col scan-list">
                        <div className="seg" role="tablist" style={{ alignSelf: 'flex-start' }}>
                            <label className="seg-opt">
                                <input type="radio" name="tab" checked={tab === 'missing'} onChange={() => setTab('missing')} />
                                Belum absen ({missing.length})
                            </label>
                            <label className="seg-opt">
                                <input type="radio" name="tab" checked={tab === 'present'} onChange={() => setTab('present')} />
                                Hadir ({present.length})
                            </label>
                        </div>

                        {tab === 'missing' ? (
                            <>
                                <p className="text-muted" style={{ fontSize: 13, margin: '12px 0 0' }}>
                                    Kartu tertinggal? Tandai hadir manual. Siswa yang tetap belum absen saat Anda menekan "Selesai" otomatis dicatat Alpa.
                                </p>
                                <div className="scan-roster">
                                    {missing.length === 0 && <p className="text-muted">Semua siswa sudah hadir.</p>}
                                    {missing.map((student) => (
                                        <div key={student.id} className="scan-roster-row">
                                            <span>
                                                {student.name}
                                                <span className="cell-sub">
                                                    NIS {student.nis}
                                                    {marks[student.id] ? ` · tercatat ${marks[student.id].statusLabel}` : ''}
                                                    {!student.hash ? ' · kartu dicabut' : ''}
                                                </span>
                                            </span>
                                            <button type="button" className="btn btn-secondary" onClick={() => markPresent(student, 'manual')}>
                                                Hadir
                                            </button>
                                        </div>
                                    ))}
                                </div>
                                {excused.length > 0 && (
                                    <p className="text-muted" style={{ fontSize: 12 }}>
                                        Siswa yang sudah tercatat Sakit/Izin tidak diubah menjadi Alpa.
                                    </p>
                                )}
                            </>
                        ) : (
                            <div className="scan-roster">
                                {present.length === 0 && <p className="text-muted">Belum ada yang absen.</p>}
                                {[...present]
                                    .sort((a, b) => marks[b.id].recordedAt.localeCompare(marks[a.id].recordedAt))
                                    .map((student) => (
                                        <div key={student.id} className="scan-roster-row">
                                            <span>
                                                {student.name}
                                                <span className="cell-sub">NIS {student.nis}</span>
                                            </span>
                                            <span className="text-muted" style={{ fontSize: 13 }}>
                                                {formatTime(marks[student.id].recordedAt)}
                                                {marks[student.id].pending ? ' · belum terkirim' : ''}
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        )}

                        <div className="scan-finish">
                            {closeError && (
                                <p className="alert alert-error" role="alert">
                                    {closeError}
                                </p>
                            )}
                            <button type="button" className="btn btn-primary auth-submit" onClick={() => setClosing(true)}>
                                Selesai absensi
                                <CheckCircle2 size={18} aria-hidden="true" />
                            </button>
                        </div>
                    </section>
                </main>
            </div>

            <ConfirmDialog open={closing} title="Selesaikan absensi kelas ini?" confirmLabel="Ya, selesai" onConfirm={finish} onClose={() => setClosing(false)}>
                {missing.length - excused.length > 0
                    ? `${missing.length - excused.length} siswa yang belum absen akan dicatat Alpa. Setelah selesai, absensi kelas ini dikunci; siswa yang datang terlambat melapor ke guru piket.`
                    : 'Semua siswa sudah tercatat. Setelah selesai, absensi kelas ini dikunci.'}
            </ConfirmDialog>
        </>
    );
}
