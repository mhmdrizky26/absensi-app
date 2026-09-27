import { Head, Link, usePoll } from '@inertiajs/react';
import { FilePen, ScanLine } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';

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

function SessionTag({ session, windowOpened }) {
    if (session.state === 'closed') {
        return <span className="tag tag-neutral">Selesai {formatTime(session.closedAt)}</span>;
    }

    if (session.state === 'open') {
        return <span className="tag tag-accent">Sedang scan sejak {formatTime(session.openedAt)}</span>;
    }

    return windowOpened ? <span className="tag tag-danger">Belum absen</span> : <span className="tag tag-outline">Belum dibuka</span>;
}

export default function Monitor({ date, window: scanWindow, classrooms, absent, late }) {
    usePoll(30000, { only: ['classrooms', 'absent', 'late'] });

    const totals = { H: 0, T: 0, S: 0, I: 0, A: 0 };
    let students = 0;

    for (const classroom of classrooms) {
        students += classroom.total;
        STATUS_LABELS.forEach(([status]) => (totals[status] += classroom.counts[status]));
    }

    const recorded = Object.values(totals).reduce((sum, count) => sum + count, 0);
    const windowOpened = new Date() >= new Date(scanWindow.opensAt);
    const notOpened = classrooms.filter((classroom) => classroom.session.state === 'not_opened');

    return (
        <>
            <Head title="Pantauan hari ini" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Pantauan hari ini</h6>
                    <h1>{dayFormatter.format(new Date(`${date}T00:00:00`))}</h1>
                </div>
                <div className="page-actions">
                    <Link href="/izin" className="btn btn-secondary">
                        <FilePen size={16} aria-hidden="true" /> Izin & sakit
                    </Link>
                    <Link href="/terlambat" className="btn btn-primary">
                        <ScanLine size={16} aria-hidden="true" /> Scan terlambat
                    </Link>
                </div>
            </div>

            {!scanWindow.isSchoolDay && (
                <p className="alert">{scanWindow.holiday ? `Hari ini libur: ${scanWindow.holiday}.` : 'Hari ini bukan hari sekolah.'} Absensi kelas tidak dibuka.</p>
            )}

            <p className="text-muted" style={{ fontSize: 14 }}>
                Scan kelas {formatTime(scanWindow.opensAt)}–{formatTime(scanWindow.closesAt)}. Kelas yang lupa menekan "Selesai" ditutup otomatis pukul {formatTime(scanWindow.closesAt)}. Halaman ini diperbarui setiap 30 detik.
            </p>

            <div className="stat-strip">
                {STATUS_LABELS.map(([status, label]) => (
                    <div key={status} className="stat">
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

            {windowOpened && scanWindow.isSchoolDay && notOpened.length > 0 && (
                <p className="alert alert-error" role="alert">
                    {notOpened.length} kelas belum absen: {notOpened.map((classroom) => classroom.name).join(', ')}. Datangi kelasnya atau hubungi guru jam pertama.
                </p>
            )}

            <div className="monitor-layout">
                <section style={{ minWidth: 0 }}>
                    <h4>Kelas</h4>
                    <div className="table-wrap">
                        <table className="table" style={{ minWidth: 640 }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 80 }}>Kelas</th>
                                    <th>Status scan</th>
                                    <th style={{ width: '34%' }}>Hadir</th>
                                    <th className="num" style={{ width: 50 }}>T</th>
                                    <th className="num" style={{ width: 50 }}>D</th>
                                    <th className="num" style={{ width: 50 }}>S/I</th>
                                    <th className="num" style={{ width: 50 }}>A</th>
                                </tr>
                            </thead>
                            <tbody>
                                {classrooms.map((classroom) => {
                                    const present = classroom.counts.H + classroom.counts.T + classroom.counts.D;
                                    const percent = classroom.total ? Math.round((present / classroom.total) * 100) : 0;

                                    return (
                                        <tr key={classroom.id}>
                                            <td style={{ fontWeight: 800 }}>{classroom.name}</td>
                                            <td>
                                                <SessionTag session={classroom.session} windowOpened={windowOpened && scanWindow.isSchoolDay} />
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
                    <h4>Alpa hari ini ({absent.length})</h4>
                    <p className="text-muted" style={{ fontSize: 13 }}>
                        Siswa yang datang terlambat di-scan lewat "Scan terlambat". Surat sakit/izin dicatat lewat "Izin & sakit".
                    </p>
                    <div className="scan-roster" style={{ borderTop: '2px solid var(--color-divider)' }}>
                        {absent.length === 0 && <p className="text-muted">Belum ada siswa Alpa.</p>}
                        {absent.map((row) => (
                            <div key={row.id} className="scan-roster-row">
                                <span style={{ width: 48, fontWeight: 800, fontSize: 13 }}>{row.classroom}</span>
                                <span style={{ flex: 1 }}>
                                    {row.name}
                                    <span className="cell-sub">NIS {row.nis}</span>
                                </span>
                            </div>
                        ))}
                    </div>

                    <h4 style={{ marginTop: 32 }}>Terlambat ({late.length})</h4>
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
        </>
    );
}

Monitor.layout = (page) => <AppLayout>{page}</AppLayout>;
