import { Head } from '@inertiajs/react';
import { Camera, RefreshCw } from 'lucide-react';
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

const IDLE_RESULT = { kind: 'idle', kicker: 'Siap', title: 'Scan kartu siswa terlambat', detail: 'Siswa dicatat Terlambat beserta jam datangnya.' };

export default function LateScan({ late: initialLate }) {
    const [late, setLate] = useState(initialLate);
    const [result, setResult] = useState(IDLE_RESULT);
    const [camera, setCamera] = useState({ state: 'idle', error: null });
    const [manualStudent, setManualStudent] = useState(null);
    const [sending, setSending] = useState(false);
    const videoRef = useRef(null);
    const stopCameraRef = useRef(null);
    const lastReadRef = useRef({ text: null, at: 0 });

    useEffect(() => () => stopCameraRef.current?.(), []);

    async function record(body) {
        setSending(true);
        setResult({ kind: 'checking', kicker: 'Memeriksa', title: 'Mencatat…', detail: '' });

        try {
            const response = await postJson('/terlambat', body);
            const student = response.student;

            if (response.result === 'recorded') {
                signal('ok');
                setResult({ kind: 'ok', kicker: `Terlambat · ${student.classroom}`, title: student.name, detail: response.message });
                setLate((current) => [student, ...current.filter((row) => row.id !== student.id)]);
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

    async function submitManual(event) {
        event.preventDefault();
        unlockAudio();
        await record({ student_id: manualStudent.id });
        setManualStudent(null);
    }

    return (
        <>
            <Head title="Scan terlambat" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Guru piket</h6>
                    <h1>Scan terlambat</h1>
                </div>
            </div>

            <div className="late-layout">
                <section className="scan-col" style={{ padding: 0 }}>
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
                                {camera.state === 'error' && <p>{camera.error}</p>}
                                <button type="button" className="btn btn-primary scan-start" onClick={startCamera} disabled={camera.state === 'starting'}>
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
                                Catat terlambat
                            </button>
                        )}
                    </form>
                </section>

                <aside style={{ minWidth: 0 }}>
                    <h4>Terlambat hari ini ({late.length})</h4>
                    <div className="scan-roster" style={{ borderTop: '2px solid var(--color-text)' }}>
                        {late.length === 0 && <p className="text-muted">Belum ada siswa terlambat.</p>}
                        {late.map((row) => (
                            <div key={row.id} className="scan-roster-row">
                                <span style={{ width: 48, fontWeight: 800, fontSize: 13 }}>{row.classroom}</span>
                                <span style={{ flex: 1 }}>
                                    {row.name}
                                    <span className="cell-sub">NIS {row.nis}</span>
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
