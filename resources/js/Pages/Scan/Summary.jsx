import { Head, Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import { isPresent } from '@/lib/attendance';

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });

/** Tidak hadir, dari yang paling perlu diperhatikan. */
const NOT_PRESENT = [
    ['A', 'Alpa'],
    ['S', 'Sakit'],
    ['I', 'Izin'],
];

/** Tetap dihitung hadir, hanya diberi tanda. */
const MARKED_PRESENT = [
    ['T', 'Terlambat'],
    ['D', 'Dispensasi'],
];

/**
 * Kelompok siswa per status, dibagi ke beberapa kolom di layar lebar.
 */
function GroupList({ groups, marks }) {
    return (
        <div className="summary-groups">
            {groups.map((group) => (
                <section key={group.status}>
                    <h5 className="class-detail-head">
                        <span className={`tag status-${group.status}`}>{group.label}</span>
                        <span className="text-muted">{group.students.length} siswa</span>
                    </h5>
                    <div className="summary-students">
                        {group.students.map((student) => (
                            <div key={student.id} className="class-detail-row">
                                <span style={{ flex: 1 }}>
                                    {student.name}
                                    <span className="cell-sub">
                                        NIS {student.nis}
                                        {marks[student.id].note ? ` · ${marks[student.id].note}` : ''}
                                    </span>
                                </span>
                            </div>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

/**
 * Hasil absensi kelas setelah guru menekan "Selesai": hanya daftar siswa
 * yang tidak tercatat Hadir. Hanya bisa dilihat; perubahan dilakukan wali
 * kelas, dan siswa terlambat lewat guru piket.
 */
export default function Summary({ classroom, session, roster, marks }) {
    const { flash } = usePage().props;
    const [filter, setFilter] = useState('all');

    const present = roster.filter((student) => isPresent(marks[student.id]?.status)).length;
    const groupsOf = (statuses) =>
        statuses.map(([status, label]) => ({ status, label, students: roster.filter((student) => marks[student.id]?.status === status) })).filter((group) => group.students.length > 0);
    const groups = groupsOf(NOT_PRESENT);
    const marked = groupsOf(MARKED_PRESENT);
    const allGroups = [...groups, ...marked];
    const notPresent = groups.reduce((sum, group) => sum + group.students.length, 0);
    const visible = (list) => list.filter((group) => filter === 'all' || group.status === filter);

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
                    <Link href="/absen/keluar" method="post" as="button" className="btn btn-secondary nav-logout" aria-label="Keluar">
                        <span className="nav-logout-label">Keluar</span>
                        <LogOut size={16} aria-hidden="true" />
                    </Link>
                </header>

                <main className="page summary-page">
                    {flash.success && (
                        <p className="alert" role="status">
                            {flash.success}
                        </p>
                    )}

                    <div className="page-head">
                        <div>
                            <h6 className="text-muted">
                                {dayFormatter.format(new Date(`${session.date}T00:00:00`))} · dikunci pukul {timeFormatter.format(new Date(session.closedAt))}
                            </h6>
                            <h1>Siswa tidak hadir</h1>
                        </div>
                    </div>

                    <p className="summary-line">
                        <b>{notPresent}</b> siswa tidak hadir · <b>{present}</b> dari {roster.length} siswa hadir di kelas {classroom.name}
                    </p>

                    {allGroups.length > 0 && (
                        <div className="class-filter" role="group" aria-label="Tampilkan status">
                            <button type="button" className={`class-filter-chip ${filter === 'all' ? 'is-active' : ''}`} aria-pressed={filter === 'all'} onClick={() => setFilter('all')}>
                                Semua <b>{allGroups.reduce((sum, group) => sum + group.students.length, 0)}</b>
                            </button>
                            {allGroups.map((group) => (
                                <button key={group.status} type="button" className={`class-filter-chip ${filter === group.status ? 'is-active' : ''}`} aria-pressed={filter === group.status} onClick={() => setFilter(group.status)}>
                                    <span className={`mark mark-${group.status}`} aria-hidden="true">
                                        {group.status}
                                    </span>
                                    {group.label} <b>{group.students.length}</b>
                                </button>
                            ))}
                        </div>
                    )}

                    {notPresent === 0 && filter === 'all' && (
                        <div className="empty">
                            <h4>Semua siswa hadir</h4>
                            <p className="text-muted">Tidak ada siswa yang alpa, sakit, atau izin hari ini.</p>
                        </div>
                    )}

                    <GroupList groups={visible(groups)} marks={marks} />

                    {visible(marked).length > 0 && (
                        <section className={filter === 'all' ? 'summary-marked' : undefined}>
                            <h4>Hadir dengan catatan</h4>
                            <p className="text-muted" style={{ fontSize: 13, marginTop: 0 }}>
                                Sudah dihitung hadir; terlambat dan dispensasi hanya sebagai tanda.
                            </p>
                            <GroupList groups={visible(marked)} marks={marks} />
                        </section>
                    )}

                    <p className="text-muted" style={{ fontSize: 13, marginTop: 24 }}>
                        Absensi kelas ini sudah dikunci. Siswa yang datang terlambat melapor ke guru piket. Perubahan Alpa menjadi Sakit atau Izin dilakukan oleh wali kelas.
                    </p>
                </main>
            </div>
        </>
    );
}
