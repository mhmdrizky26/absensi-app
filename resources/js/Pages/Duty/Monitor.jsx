import { Head, Link, router, usePoll } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Paperclip, RotateCcw, ScanLine } from 'lucide-react';
import { useEffect, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Modal from '@/Components/Modal';
import AppLayout from '@/Layouts/AppLayout';
import { presentCount } from '@/lib/attendance';
import { getJson } from '@/lib/http';

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const formatTime = (iso) => timeFormatter.format(new Date(iso));

const STATUS_LABELS = [
    ['H', 'Hadir'],
    ['T', 'Terlambat'],
    ['D', 'Dispensasi'],
    ['S', 'Sakit'],
    ['I', 'Izin'],
    ['A', 'Alpa'],
];

function ClassStateTag({ classroom, phase }) {
    const missing = Math.max(classroom.total - Object.values(classroom.counts).reduce((sum, count) => sum + count, 0), 0);

    if (phase === 'before') {
        return <span className="tag tag-outline">Gerbang belum dibuka</span>;
    }

    if (missing === 0) {
        return <span className="tag tag-neutral">Semua tercatat</span>;
    }

    return <span className={`tag ${phase === 'open' ? 'tag-outline' : 'tag-danger'}`}>{missing} belum absen</span>;
}

/**
 * Kelompok di detail kelas. Terlambat masuk kelompok Hadir (hanya diberi tanda);
 * dispensasi dihitung hadir tetapi siswanya tidak di kelas, jadi dipisah.
 */
const DETAIL_GROUPS = [
    { key: 'H', label: 'Hadir', statuses: ['H', 'T'] },
    { key: 'D', label: 'Dispensasi', statuses: ['D'] },
    { key: 'S', label: 'Sakit', statuses: ['S'] },
    { key: 'I', label: 'Izin', statuses: ['I'] },
    { key: 'A', label: 'Alpa', statuses: ['A'] },
    { key: 'none', label: 'Belum tercatat', statuses: [null] },
];

/**
 * Daftar siswa satu kelas hari ini, dikelompokkan per status. Dimuat ulang
 * diam-diam setiap kali jumlah per status kelas ini berubah (scan, izin,
 * dispensasi) selama jendela masih terbuka.
 */
function ClassroomDetail({ classroom, onClose }) {
    const [detail, setDetail] = useState(null);
    const [failed, setFailed] = useState(false);
    const [filter, setFilter] = useState('all');
    const countsKey = JSON.stringify(classroom.counts);

    useEffect(() => {
        let cancelled = false;

        getJson(`/pantauan/kelas/${classroom.id}`)
            .then((data) => {
                if (!cancelled) {
                    setDetail(data);
                    setFailed(false);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setFailed(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [classroom.id, countsKey]);

    const groups = detail
        ? DETAIL_GROUPS.map((group) => ({ ...group, status: group.key === 'none' ? null : group.key, students: detail.students.filter((student) => group.statuses.includes(student.status)) })).filter(
              (group) => group.students.length > 0,
          )
        : [];
    const shown = groups.filter((group) => filter === 'all' || group.key === filter);

    return (
        <Modal open title={`Kelas ${classroom.name}`} onClose={onClose} width={560}>
            <p className="text-muted" style={{ margin: '0 0 16px', fontSize: 14 }}>
                {classroom.homeroomTeacher ? `Wali kelas ${classroom.homeroomTeacher} · ` : ''}
                {classroom.total} siswa
            </p>

            {failed && <p className="alert alert-error">Data kelas gagal dimuat. Tutup lalu coba lagi.</p>}
            {!detail && !failed && <p className="text-muted">Memuat…</p>}

            {groups.length > 0 && (
                <div className="class-filter" role="group" aria-label="Tampilkan status">
                    <button type="button" className={`class-filter-chip ${filter === 'all' ? 'is-active' : ''}`} aria-pressed={filter === 'all'} onClick={() => setFilter('all')}>
                        Semua <b>{detail.students.length}</b>
                    </button>
                    {groups.map((group) => (
                        <button key={group.key} type="button" className={`class-filter-chip ${filter === group.key ? 'is-active' : ''}`} aria-pressed={filter === group.key} onClick={() => setFilter(group.key)}>
                            <span className={`mark ${group.status ? `mark-${group.status}` : 'mark-empty'}`} aria-hidden="true">
                                {group.status ?? '·'}
                            </span>
                            {group.label} <b>{group.students.length}</b>
                        </button>
                    ))}
                </div>
            )}

            <div className="class-detail">
                {shown.map((group) => (
                    <section key={group.key}>
                        <h5 className="class-detail-head">
                            {group.status ? <span className={`tag status-${group.status}`}>{group.label}</span> : <span className="tag tag-outline">{group.label}</span>}
                            <span className="text-muted">{group.students.length} siswa</span>
                        </h5>
                        {group.students.map((student) => (
                            <div key={student.id} className="class-detail-row">
                                <span style={{ flex: 1 }}>
                                    {student.name}
                                    <span className="cell-sub">
                                        NIS {student.nis}
                                        {student.studyRoom ? ` · belajar di ${student.studyRoom}` : ''}
                                        {student.note ? ` · ${student.note}` : ''}
                                        {student.attachmentId && (
                                            <>
                                                {' · '}
                                                <a href={`/surat/${student.attachmentId}`} target="_blank" rel="noreferrer">
                                                    <Paperclip size={12} aria-hidden="true" /> surat
                                                </a>
                                            </>
                                        )}
                                    </span>
                                </span>
                                {student.status === 'T' && <span className="tag status-T">Terlambat</span>}
                                {student.recordedAt && (
                                    <span className="text-muted" style={{ fontSize: 13 }}>
                                        {formatTime(student.recordedAt)}
                                    </span>
                                )}
                            </div>
                        ))}
                    </section>
                ))}
            </div>

            <div className="dialog-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>
                    Tutup
                </button>
            </div>
        </Modal>
    );
}

/**
 * Keadaan gerbang hari ini: belum dibuka, sedang dibuka, atau sudah ditutup.
 */
function gatePhase(scanWindow) {
    if (!scanWindow.isEnforced) {
        return 'open';
    }

    const now = new Date();

    if (now < new Date(scanWindow.opensAt)) {
        return 'before';
    }

    return now < new Date(scanWindow.closesAt) ? 'open' : 'closed';
}

export default function Monitor({ date, window: scanWindow, finalized, missingCount, stations, classrooms, late }) {
    // Status gerbang ikut berubah karena waktu berjalan, jadi tetap diperbarui walau data tidak berubah.
    usePoll(12000, { only: ['classrooms', 'late', 'stations', 'missingCount', 'finalized'] });
    const [openClassroomId, setOpenClassroomId] = useState(null);
    // Ambil dari props terbaru supaya jendela detail ikut berubah saat data kelas diperbarui.
    const openClassroom = classrooms.find((classroom) => classroom.id === openClassroomId) ?? null;
    const [confirmingFinish, setConfirmingFinish] = useState(false);
    const [confirmingReopen, setConfirmingReopen] = useState(false);

    const totals = Object.fromEntries(STATUS_LABELS.map(([status]) => [status, 0]));
    let students = 0;

    for (const classroom of classrooms) {
        students += classroom.total;
        STATUS_LABELS.forEach(([status]) => (totals[status] += classroom.counts[status]));
    }

    const recorded = Object.values(totals).reduce((sum, count) => sum + count, 0);
    const phase = gatePhase(scanWindow);
    const pendingScans = stations.reduce((sum, station) => sum + station.pendingScans, 0);
    const offlineStations = stations.filter((station) => !station.isOnline);
    // Mode demo: absen boleh diselesaikan kapan saja, juga di luar hari sekolah.
    const showFinish = !finalized && (scanWindow.isSchoolDay || !scanWindow.isEnforced);
    const canFinish = showFinish && (!scanWindow.isEnforced || phase === 'closed');

    function reopen() {
        router.delete('/pantauan/selesaikan', { preserveScroll: true, onFinish: () => setConfirmingReopen(false) });
    }

    function finish() {
        router.post('/pantauan/selesaikan', {}, { preserveScroll: true, onFinish: () => setConfirmingFinish(false) });
    }

    return (
        <>
            <Head title="Pantauan hari ini" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Pantauan hari ini</h6>
                    <h1>{dayFormatter.format(new Date(`${date}T00:00:00`))}</h1>
                </div>
                <div className="page-actions">
                    <Link href="/terlambat" className="btn btn-secondary">
                        <ScanLine size={16} aria-hidden="true" /> Absen piket
                    </Link>
                    {finalized && !scanWindow.isEnforced && !scanWindow.onlineDay && (
                        <button type="button" className="btn btn-secondary" onClick={() => setConfirmingReopen(true)}>
                            <RotateCcw size={16} aria-hidden="true" /> Buka lagi absen hari ini
                        </button>
                    )}
                    {showFinish && (
                        <button type="button" className="btn btn-primary" disabled={!canFinish} title={canFinish ? undefined : 'Bisa ditekan setelah jam masuk'} onClick={() => setConfirmingFinish(true)}>
                            <CheckCircle2 size={16} aria-hidden="true" /> Selesaikan absen
                        </button>
                    )}
                </div>
            </div>

            {!scanWindow.isSchoolDay && <p className="alert">{scanWindow.holiday ? `Hari ini libur: ${scanWindow.holiday}.` : 'Hari ini bukan hari sekolah.'} Gerbang tidak dibuka.</p>}

            {scanWindow.onlineDay ? (
                <p className="alert">
                    Hari ini sekolah daring: {scanWindow.onlineDay}. Gerbang tidak dibuka dan semua siswa otomatis dicatat Hadir. Koreksi (mis. sakit/izin) lewat wali kelas.
                </p>
            ) : finalized ? (
                <p className="alert">
                    Absen hari ini sudah diselesaikan pukul {formatTime(finalized.at)}
                    {finalized.by ? ` oleh ${finalized.by}` : ' otomatis'}. {finalized.absentCount} siswa dicatat Alpa.{' '}
                    {scanWindow.isEnforced ? 'Perubahan berikutnya lewat koreksi wali kelas.' : 'Mode demo: tekan "Buka lagi absen hari ini" untuk mengulang latihan.'}
                </p>
            ) : (
                scanWindow.isSchoolDay && (
                    <p className="text-muted" style={{ fontSize: 14 }}>
                        Gerbang dibuka {formatTime(scanWindow.opensAt)}, jam masuk {formatTime(scanWindow.closesAt)}. Setelah jam masuk, catat siswa terlambat dan yang lupa kartu lewat &quot;Absen piket&quot;, lalu tekan
                        &quot;Selesaikan absen&quot;: siswa yang belum absen dicatat Alpa. Kalau lupa ditekan, absen diselesaikan otomatis malam hari. Klik kelas untuk melihat daftar siswanya.
                    </p>
                )
            )}

            {stations.length > 0 && scanWindow.isSchoolDay && !finalized && (
                <div className="station-strip" aria-label="Tab gerbang">
                    {stations.map((station) => (
                        <span key={station.id} className={`tag ${station.isOnline ? 'tag-neutral' : 'tag-outline'}`}>
                            {station.name} · {station.isOnline ? 'online' : 'offline'}
                            {station.pendingScans > 0 ? ` · ${station.pendingScans} belum terkirim` : ''}
                        </span>
                    ))}
                </div>
            )}

            <div className="stat-strip">
                <div className="stat status-H">
                    <div className="stat-label">Hadir</div>
                    <div className="stat-value">{presentCount(totals)}</div>
                    {(totals.T > 0 || totals.D > 0) && (
                        <div className="stat-delta">
                            termasuk {totals.T} terlambat · {totals.D} dispensasi
                        </div>
                    )}
                </div>
                {STATUS_LABELS.filter(([status]) => ['S', 'I', 'A'].includes(status)).map(([status, label]) => (
                    <div key={status} className={`stat status-${status}`}>
                        <div className="stat-label">{label}</div>
                        <div className="stat-value" style={status === 'A' && totals.A > 0 ? { color: 'var(--color-danger)' } : undefined}>
                            {totals[status]}
                        </div>
                    </div>
                ))}
                <div className="stat">
                    <div className="stat-label">Belum tercatat</div>
                    <div className="stat-value">{Math.max(students - recorded, 0)}</div>
                </div>
            </div>

            <div className="monitor-layout">
                <section style={{ minWidth: 0 }}>
                    <h4>Kelas</h4>
                    <div className="table-wrap">
                        <table className="table" style={{ minWidth: 640 }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 80 }}>Kelas</th>
                                    <th>Status</th>
                                    <th style={{ width: '34%' }}>Hadir</th>
                                    <th className="num" style={{ width: 50 }}>T</th>
                                    <th className="num" style={{ width: 50 }}>D</th>
                                    <th className="num" style={{ width: 50 }}>S/I</th>
                                    <th className="num" style={{ width: 50 }}>A</th>
                                </tr>
                            </thead>
                            <tbody>
                                {classrooms.map((classroom) => {
                                    const present = presentCount(classroom.counts);
                                    const percent = classroom.total ? Math.round((present / classroom.total) * 100) : 0;

                                    return (
                                        <tr key={classroom.id} className="row-link" onClick={() => setOpenClassroomId(classroom.id)}>
                                            <td style={{ fontWeight: 800 }}>
                                                <button type="button" className="row-link-button" onClick={() => setOpenClassroomId(classroom.id)}>
                                                    {classroom.name} <ChevronRight size={14} aria-hidden="true" />
                                                </button>
                                            </td>
                                            <td>
                                                {scanWindow.isSchoolDay ? <ClassStateTag classroom={classroom} phase={finalized ? 'closed' : phase} /> : '—'}
                                            </td>
                                            <td>
                                                <div className="bar-cell">
                                                    <div className="bar">
                                                        <div style={{ width: `${percent}%` }} />
                                                    </div>
                                                    <span>
                                                        {present}/{classroom.total}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="num">{classroom.counts.T || '—'}</td>
                                            <td className="num">{classroom.counts.D || '—'}</td>
                                            <td className="num">{classroom.counts.S + classroom.counts.I || '—'}</td>
                                            <td className="num" style={classroom.counts.A ? { color: 'var(--color-danger)', fontWeight: 800 } : undefined}>
                                                {classroom.counts.A || '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside style={{ minWidth: 0 }}>
                    <h4>Terlambat ({late.length})</h4>
                    <p className="text-muted" style={{ fontSize: 13 }}>
                        Siswa yang datang setelah jam masuk dicatat lewat &quot;Absen piket&quot;.
                    </p>
                    <div className="scan-roster" style={{ borderTop: '2px solid var(--color-divider)' }}>
                        {late.length === 0 && <p className="text-muted">Belum ada siswa terlambat.</p>}
                        {late.map((row) => (
                            <div key={row.id} className="scan-roster-row">
                                <span style={{ width: 48, fontWeight: 800, fontSize: 13 }}>{row.classroom}</span>
                                <span style={{ flex: 1 }}>{row.name}</span>
                                <span className="text-muted" style={{ fontSize: 13 }}>
                                    {formatTime(row.recordedAt)}
                                </span>
                            </div>
                        ))}
                    </div>
                </aside>
            </div>

            {openClassroom && <ClassroomDetail classroom={openClassroom} onClose={() => setOpenClassroomId(null)} />}

            <ConfirmDialog open={confirmingReopen} title="Buka lagi absen hari ini?" confirmLabel="Buka lagi" onConfirm={reopen} onClose={() => setConfirmingReopen(false)}>
                Khusus mode demo. Tanda Alpa yang dibuat saat absen diselesaikan dihapus, lalu tab gerbang dan Absen piket menerima absen lagi. Hadir, terlambat, sakit, dan izin yang sudah tercatat tidak berubah.
            </ConfirmDialog>

            <ConfirmDialog open={confirmingFinish} title="Selesaikan absen hari ini?" confirmLabel="Ya, selesaikan" onConfirm={finish} onClose={() => setConfirmingFinish(false)}>
                {missingCount > 0 ? `${missingCount} siswa yang belum absen akan dicatat Alpa.` : 'Semua siswa sudah tercatat.'} Setelah diselesaikan, tab gerbang dan Absen piket tidak menerima absen hari ini lagi; perubahan
                dilakukan wali kelas.
                {pendingScans > 0 && ` Perhatian: masih ada ${pendingScans} scan di tab gerbang yang belum terkirim. Tunggu sampai terkirim dulu.`}
                {offlineStations.length > 0 && pendingScans === 0 && ` ${offlineStations.map((station) => station.name).join(', ')} sedang offline; pastikan tidak ada scan yang tertahan di tab itu.`}
            </ConfirmDialog>
        </>
    );
}

Monitor.layout = (page) => <AppLayout>{page}</AppLayout>;
