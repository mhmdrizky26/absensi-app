import { Head, Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });

const STATUS_ORDER = ['A', 'S', 'I', 'D', 'T', 'H'];

/**
 * Hasil absensi kelas setelah guru menekan "Selesai". Hanya bisa dilihat;
 * perubahan dilakukan wali kelas, dan siswa terlambat lewat guru piket.
 */
export default function Summary({ classroom, session, roster, marks }) {
    const { flash } = usePage().props;

    const counts = { H: 0, T: 0, D: 0, S: 0, I: 0, A: 0 };
    roster.forEach((student) => {
        const status = marks[student.id]?.status;

        if (status) {
            counts[status]++;
        }
    });

    const notPresent = roster
        .filter((student) => marks[student.id] && !['H', 'T'].includes(marks[student.id].status))
        .sort((a, b) => STATUS_ORDER.indexOf(marks[a.id].status) - STATUS_ORDER.indexOf(marks[b.id].status));

    return (
        <>
            <Head title={`Absensi ${classroom.name} selesai`} />

            <div className="scan-app">
                <header className="nav scan-nav">
                    <span className="nav-brand">
                        Absensi <span className="scan-nav-sub">/ Scan kelas</span>
                    </span>
                    <span className="scan-class">
                        <b>Kelas {classroom.name}</b>
                    </span>
                    <Link href="/absen/keluar" method="post" as="button" className="btn btn-secondary">
                        <Lock size={16} aria-hidden="true" /> Keluar
                    </Link>
                </header>

                <main className="page">
                    {flash.success && (
                        <p className="alert" role="status">
                            {flash.success}
                        </p>
                    )}

                    <div className="page-head">
                        <div>
                            <h6 className="text-muted">
                                {dayFormatter.format(new Date(`${session.date}T00:00:00`))} · ditutup pukul {timeFormatter.format(new Date(session.closedAt))}
                            </h6>
                            <h1>Absensi {classroom.name} selesai</h1>
                        </div>
                    </div>

                    <div className="summary-layout">
                        <section>
                            <div className="stat-strip is-compact">
                                {[
                                    ['H', 'Hadir'],
                                    ['T', 'Terlambat'],
                                    ['D', 'Dispensasi'],
                                    ['S', 'Sakit'],
                                    ['I', 'Izin'],
                                    ['A', 'Alpa'],
                                ].map(([status, label]) => (
                                    <div key={status} className="stat">
                                        <div className="stat-label">{label}</div>
                                        <div className="stat-value" style={status === 'A' && counts.A > 0 ? { color: 'var(--color-accent)' } : undefined}>
                                            {counts[status]}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <p className="text-muted" style={{ fontSize: 13 }}>
                                Absensi kelas ini sudah dikunci. Siswa yang datang terlambat melapor ke guru piket. Perubahan Alpa menjadi Sakit atau Izin dilakukan oleh wali kelas.
                            </p>
                        </section>

                        <section>
                            <h4>Tidak hadir di kelas</h4>
                            {notPresent.length === 0 ? (
                                <p className="text-muted">Semua siswa hadir.</p>
                            ) : (
                                <div className="scan-roster" style={{ borderTop: '2px solid var(--color-text)' }}>
                                    {notPresent.map((student) => (
                                        <div key={student.id} className="scan-roster-row">
                                            <span>
                                                {student.name}
                                                <span className="cell-sub">NIS {student.nis}</span>
                                            </span>
                                            <span className={`tag status-${marks[student.id].status}`}>{marks[student.id].statusLabel}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}
