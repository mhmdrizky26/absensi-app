import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { useState } from 'react';

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
const formatTime = (iso) => timeFormatter.format(new Date(iso));

/**
 * Urutan kelompok: yang ada di kelas dulu, lalu yang tidak.
 */
const GROUPS = [
    { key: 'hadir', label: 'Hadir', statuses: ['H', 'T'] },
    { key: 'D', label: 'Dispensasi', statuses: ['D'] },
    { key: 'S', label: 'Sakit', statuses: ['S'] },
    { key: 'I', label: 'Izin', statuses: ['I'] },
    { key: 'A', label: 'Alpa', statuses: ['A'] },
    { key: 'none', label: 'Belum absen', statuses: [null] },
];

export default function ClassViewShow({ classroom, date, isSchoolDay, isFinalized, students }) {
    const { appName } = usePage().props;
    usePoll(30000, { only: ['students', 'isFinalized'] });
    const [filter, setFilter] = useState('all');

    const groups = GROUPS.map((group) => ({ ...group, students: students.filter((student) => group.statuses.includes(student.status)) })).filter((group) => group.students.length > 0);
    const shown = groups.filter((group) => filter === 'all' || group.key === filter);

    return (
        <>
            <Head title={`Kelas ${classroom.name}`} />

            <div className="scan-app">
                <header className="nav scan-nav">
                    <span className="nav-brand">
                        {appName} <span className="scan-nav-sub">/ Lihat kelas</span>
                    </span>
                    <span className="scan-class">
                        <b>Kelas {classroom.name}</b>
                        <span className="scan-class-date"> · {dayFormatter.format(new Date(`${date}T00:00:00`))}</span>
                    </span>
                    <Link href="/lihat-kelas/ganti" method="post" as="button" className="btn btn-secondary">
                        <ArrowLeftRight size={16} aria-hidden="true" /> Ganti kelas
                    </Link>
                </header>

                <main className="page" style={{ maxWidth: 720 }}>
                    {!isSchoolDay && <p className="alert">Hari ini bukan hari sekolah.</p>}
                    <p className="text-muted" style={{ fontSize: 14 }}>
                        {classroom.hasRoom
                            ? 'Siswa yang belajar di ruang ini hari ini, termasuk siswa kelas lain yang dititipkan di sini.'
                            : 'Kelas ini tidak punya ruang sendiri; tiap siswa tertulis belajar di kelas mana.'}{' '}
                        {isFinalized ? 'Absen hari ini sudah diselesaikan guru piket.' : 'Siswa "Belum absen" belum scan di gerbang; mungkin terlambat dan belum dicatat guru piket.'} Halaman ini diperbarui sendiri setiap 30 detik.
                    </p>

                    {students.length === 0 ? (
                        <p className="text-muted">Belum ada siswa di kelas ini.</p>
                    ) : (
                        <>
                            <div className="class-filter" role="group" aria-label="Tampilkan status">
                                <button type="button" className={`class-filter-chip ${filter === 'all' ? 'is-active' : ''}`} aria-pressed={filter === 'all'} onClick={() => setFilter('all')}>
                                    Semua <b>{students.length}</b>
                                </button>
                                {groups.map((group) => (
                                    <button key={group.key} type="button" className={`class-filter-chip ${filter === group.key ? 'is-active' : ''}`} aria-pressed={filter === group.key} onClick={() => setFilter(group.key)}>
                                        {group.label} <b>{group.students.length}</b>
                                    </button>
                                ))}
                            </div>

                            <div className="class-detail">
                                {shown.map((group) => (
                                    <section key={group.key}>
                                        <h5 className="class-detail-head">
                                            {group.key === 'none' ? <span className="tag tag-outline">{group.label}</span> : <span className={`tag status-${group.statuses[0]}`}>{group.label}</span>}
                                            <span className="text-muted">{group.students.length} siswa</span>
                                        </h5>
                                        {group.students.map((student) => (
                                            <div key={student.id} className="class-detail-row">
                                                <span style={{ flex: 1 }}>
                                                    {student.name}
                                                    <span className="cell-sub">
                                                        NIS {student.nis}
                                                        {student.from ? ` · siswa ${student.from}` : ''}
                                                        {student.studyIn ? ` · belajar di ${student.studyIn}` : ''}
                                                    </span>
                                                </span>
                                                {student.status === 'T' && <span className="tag status-T">Terlambat</span>}
                                                {student.recordedAt && ['H', 'T'].includes(student.status) && (
                                                    <span className="text-muted" style={{ fontSize: 13 }}>
                                                        {formatTime(student.recordedAt)}
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </section>
                                ))}
                            </div>
                        </>
                    )}
                </main>
            </div>
        </>
    );
}
