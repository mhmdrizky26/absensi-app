import { Head, Link } from '@inertiajs/react';
import { Camera, RefreshCw, SwitchCamera } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Field from '@/Components/Field';
import StudentPicker from '@/Components/StudentPicker';
import AppLayout from '@/Layouts/AppLayout';
import { signal, unlockAudio } from '@/lib/feedback';
import { postJson } from '@/lib/http';
import { CameraError, startScanner } from '@/lib/qrScanner';

const SAME_CARD_COOLDOWN_MS = 3000;
const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const formatTime = (iso) => timeFormatter.format(new Date(iso));

function idleResult(closesAt) {
    const isLateNow = closesAt && new Date() >= new Date(closesAt);

    return {
        kind: 'idle',
        kicker: 'Siap',
        title: isLateNow ? 'Scan kartu siswa terlambat' : 'Scan kartu siswa',
        detail: isLateNow ? 'Siswa dicatat Terlambat beserta jam datangnya.' : `Sebelum jam masuk${closesAt ? ` (${formatTime(closesAt)})` : ''} siswa dicatat Hadir; setelahnya Terlambat.`,
    };
}

export default function LateScan({ recorded: initialRecorded, opensAt, closesAt, isSchoolDay, isFinalized, scanUntil }) {
    const [recorded, setRecorded] = useState(initialRecorded);
    const [now, setNow] = useState(() => new Date());
    const isBeforeOpening = opensAt !== null && now < new Date(opensAt);
    const isPastScanUntil = scanUntil !== null && now >= new Date(scanUntil);
    const canRecord = isSchoolDay && !isFinalized && !isBeforeOpening && !isPastScanUntil;
    const waitsForClock = isBeforeOpening || (scanUntil !== null && !isPastScanUntil);
    const IDLE_RESULT = idleResult(closesAt);
    const [result, setResult] = useState(IDLE_RESULT);
    const [camera, setCamera] = useState({ state: 'idle', error: null });
    const [facing, setFacing] = useState('environment');
    const [manualStudent, setManualStudent] = useState(null);
    const [sending, setSending] = useState(false);
    const videoRef = useRef(null);
    const stopCameraRef = useRef(null);
    const lastReadRef = useRef({ text: null, at: 0 });

    useEffect(() => () => stopCameraRef.current?.(), []);

    // Tombol scan muncul sendiri saat jam buka, dan untuk sapa pagi hilang saat jam masuk, tanpa memuat ulang halaman.
    useEffect(() => {
        if (!waitsForClock) {
            return undefined;
        }

        const timer = setInterval(() => setNow(new Date()), 15000);

        return () => clearInterval(timer);
    }, [waitsForClock]);

    useEffect(() => {
        if (!canRecord) {
            stopCameraRef.current?.();
            stopCameraRef.current = null;
        }
    }, [canRecord]);

    async function record(body) {
        setSending(true);
        setResult({ kind: 'checking', kicker: 'Memeriksa', title: 'Mencatat…', detail: '' });

        try {
            const response = await postJson('/terlambat', body);
            const student = response.student;

            if (response.result === 'recorded') {
                signal('ok');
                setResult({ kind: 'ok', kicker: `${student.statusLabel} · ${student.classroom}`, title: student.name, detail: response.message });
                setRecorded((current) => [student, ...current.filter((row) => row.id !== student.id)]);
            } else if (response.result === 'duplicate') {
                signal('duplicate');
                setResult({ kind: 'duplicate', kicker: `Sudah tercatat · ${student.classroom}`, title: student.name, detail: response.message });
            } else {
                signal('error');
                setResult({ kind: 'error', kicker: 'Ditolak', title: 'Tidak dicatat', detail: response.message });
            }
        } catch {
            signal('error');
            setResult({ kind: 'error', kicker: 'Gagal', title: 'Tidak tersambung ke server', detail: 'Periksa internet lalu scan ulang.' });
        } finally {
            setSending(false);
        }
    }

    function handleRead(text) {
        const payload = text.trim().toUpperCase();
        const now = Date.now();

        if (lastReadRef.current.text === payload && now - lastReadRef.current.at < SAME_CARD_COOLDOWN_MS) {
            return;
        }

        lastReadRef.current = { text: payload, at: now };
        record({ payload });
    }

    const handleReadRef = useRef(handleRead);
    handleReadRef.current = handleRead;

    async function startCamera(nextFacing = facing) {
        unlockAudio();
        stopCameraRef.current?.();
        stopCameraRef.current = null;
        setFacing(nextFacing);
        setCamera({ state: 'starting', error: null });

        try {
            stopCameraRef.current = await startScanner(videoRef.current, (text) => handleReadRef.current(text), nextFacing);
            setCamera({ state: 'on', error: null });
        } catch (error) {
            setCamera({ state: 'error', error: error instanceof CameraError ? error.message : 'Kamera tidak bisa dibuka.' });
        }
    }

    async function submitManual(event) {
        event.preventDefault();
        unlockAudio();
        await record({ student_id: manualStudent.id });
        setManualStudent(null);
    }

    return (
        <>
            <Head title="Scan absen" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Piket & sapa pagi</h6>
                    <h1>Scan absen</h1>
                </div>
            </div>

            {isFinalized ? (
                <p className="alert">
                    Absen hari ini sudah diselesaikan. Siswa yang datang sekarang dikoreksi wali kelas lewat <Link href="/rekap">rekap</Link>.
                </p>
            ) : scanUntil ? (
                <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                    Sapa pagi: catat siswa yang lupa membawa kartu, sampai jam masuk ({formatTime(scanUntil)}). Setelah itu siswa yang terlambat dicatat guru piket.
                </p>
            ) : (
                <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                    Untuk siswa yang lupa membawa kartu atau datang setelah gerbang ditutup. Setelah semua dicatat, tekan "Selesaikan absen" di halaman <Link href="/pantauan">Pantauan</Link>.
                </p>
            )}

            <div className="late-layout">
                {canRecord ? (
                    <section className="scan-col" style={{ padding: 0 }}>
                        <div className="scan-camera">
                            <video ref={videoRef} className="scan-video" muted playsInline />
                            {camera.state === 'on' && (
                                <button type="button" className="scan-switch" onClick={() => startCamera(facing === 'user' ? 'environment' : 'user')}>
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
                                    {camera.state === 'error' && <p>{camera.error}</p>}
                                    <button type="button" className="btn btn-primary scan-start" onClick={() => startCamera()} disabled={camera.state === 'starting'}>
                                        {camera.state === 'error' ? <RefreshCw size={18} aria-hidden="true" /> : <Camera size={20} aria-hidden="true" />}
                                        {camera.state === 'starting' ? 'Membuka kamera…' : camera.state === 'error' ? 'Coba lagi' : 'Mulai scan'}
                                    </button>
                                </div>
                            )}
                        </div>

                        <div className={`scan-result is-${result.kind}`} role="status" aria-live="assertive">
                            <div className="scan-result-kicker">{result.kicker}</div>
                            <div className="scan-result-title">{result.title}</div>
                            <div className="scan-result-detail">{result.detail}</div>
                        </div>

                        <form onSubmit={submitManual} className="late-manual">
                            <Field label="Kartu tertinggal? Cari siswanya" htmlFor="manual-student">
                                <StudentPicker id="manual-student" value={manualStudent} onChange={setManualStudent} />
                            </Field>
                            {manualStudent && (
                                <button type="submit" className="btn btn-primary" disabled={sending}>
                                    Catat absen
                                </button>
                            )}
                        </form>
                    </section>
                ) : (
                    <section className="scan-col">
                        <p className="alert" style={{ margin: 0 }}>
                            {isFinalized
                                ? 'Scan absen ditutup karena absen hari ini sudah diselesaikan.'
                                : !isSchoolDay
                                  ? 'Hari ini bukan hari sekolah, jadi scan absen tidak dibuka.'
                                  : isPastScanUntil
                                    ? `Scan sapa pagi ditutup pukul ${formatTime(scanUntil)}. Siswa yang datang terlambat dicatat guru piket.`
                                    : `Scan absen baru dibuka pukul ${formatTime(opensAt)}, sama dengan tab gerbang. Tombol scan muncul otomatis pada jamnya.`}
                        </p>
                    </section>
                )}

                <aside style={{ minWidth: 0 }}>
                    <h4>Dicatat lewat scan absen hari ini ({recorded.length})</h4>
                    <div className="scan-roster" style={{ borderTop: '2px solid var(--color-text)' }}>
                        {recorded.length === 0 && <p className="text-muted">Belum ada siswa yang dicatat lewat scan absen.</p>}
                        {recorded.map((row) => (
                            <div key={row.id} className="scan-roster-row">
                                <span style={{ width: 48, fontWeight: 800, fontSize: 13 }}>{row.classroom}</span>
                                <span style={{ flex: 1 }}>
                                    {row.name}
                                    <span className="cell-sub">
                                        NIS {row.nis} · {row.statusLabel}
                                    </span>
                                </span>
                                <span className="text-muted" style={{ fontSize: 13 }}>
                                    {formatTime(row.recordedAt)}
                                </span>
                            </div>
                        ))}
                    </div>
                </aside>
            </div>
        </>
    );
}

LateScan.layout = (page) => <AppLayout>{page}</AppLayout>;
