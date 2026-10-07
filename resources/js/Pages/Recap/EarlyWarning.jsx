import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StudentRecapModal from '@/Components/StudentRecapModal';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

const LEVELS = {
    tinggi: { label: 'Risiko tinggi', className: 'tag-danger' },
    perhatian: { label: 'Perlu perhatian', className: 'tag-warning' },
};

export default function EarlyWarning({ students, rules, period, classrooms, filters, homeroom, isHomeroomTeacher }) {
    const { auth } = usePage().props;
    const [level, setLevel] = useState('semua');
    const [openStudent, setOpenStudent] = useState(null);
    const high = students.filter((student) => student.level === 'tinggi').length;
    const shown = level === 'semua' ? students : students.filter((student) => student.level === level);

    return (
        <>
            <Head title="Siswa berisiko" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        Peringatan dini{homeroom ? ` · Kelas ${homeroom}` : ''}
                        {period ? ` · ${period.label}` : ''}
                    </h6>
                    <h1>Siswa berisiko</h1>
                </div>
                {classrooms.length > 0 && (
                    <div className="page-actions">
                        <select
                            className="input"
                            style={{ width: 'auto' }}
                            aria-label="Kelas"
                            value={filters.classroom ?? ''}
                            onChange={(event) => router.get('/siswa-berisiko', { classroom: event.target.value || undefined }, { preserveScroll: true, replace: true })}
                        >
                            <option value="">Semua kelas</option>
                            {classrooms.map((classroom) => (
                                <option key={classroom.id} value={classroom.id}>
                                    Kelas {classroom.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            {isHomeroomTeacher && !homeroom ? (
                <p className="alert alert-error">Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif. Hubungi admin.</p>
            ) : (
                <>
                    <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                        Sistem memeriksa absensi {period ? `${formatDate(period.from)} s.d. ${formatDate(period.to)}` : 'semester ini'} dengan aturan tetap di bawah, lalu menampilkan siswa yang perlu didekati wali kelas atau guru BK. Daftar ini bahan tindak lanjut, bukan hukuman.
                    </p>

                    <div className="stat-strip is-compact" style={{ maxWidth: 520 }}>
                        <div className="stat status-A">
                            <div className="stat-label">Risiko tinggi</div>
                            <div className="stat-value">{high}</div>
                        </div>
                        <div className="stat status-T">
                            <div className="stat-label">Perlu perhatian</div>
                            <div className="stat-value">{students.length - high}</div>
                        </div>
                    </div>

                    <div className="early-layout">
                        <section style={{ minWidth: 0 }}>
                            <div className="toolbar" style={{ marginBottom: 12 }}>
                                <div className="seg" role="radiogroup" aria-label="Tingkat risiko">
                                    {[
                                        ['semua', `Semua (${students.length})`],
                                        ['tinggi', `Tinggi (${high})`],
                                        ['perhatian', `Perhatian (${students.length - high})`],
                                    ].map(([value, text]) => (
                                        <label key={value} className="seg-opt">
                                            <input type="radio" name="level" checked={level === value} onChange={() => setLevel(value)} />
                                            {text}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {shown.length === 0 ? (
                                <div className="empty">
                                    <h4>Tidak ada siswa di daftar ini</h4>
                                    <p className="text-muted">Belum ada siswa yang memenuhi aturan peringatan dini.</p>
                                </div>
                            ) : (
                                <div className="risk-list">
                                    {shown.map((student) => (
                                        <article key={student.id} className={`risk-card is-${student.level}`}>
                                            <header className="risk-head">
                                                <div>
                                                    <b>{student.name}</b>
                                                    <span className="cell-sub">
                                                        Kelas {student.classroom} · NIS {student.nis}
                                                    </span>
                                                </div>
                                                <span className={`tag ${LEVELS[student.level].className}`}>{LEVELS[student.level].label}</span>
                                            </header>

                                            <ul className="risk-reasons">
                                                {student.reasons.map((reason) => (
                                                    <li key={reason.text} className={`is-${reason.level}`}>
                                                        {reason.text}
                                                    </li>
                                                ))}
                                            </ul>

                                            <footer className="risk-foot">
                                                <span className="risk-recent" aria-label="Status 10 hari sekolah terakhir">
                                                    {student.recent.map((day) => (
                                                        <span key={day.date} className={`mark ${day.status ? `mark-${day.status}` : 'mark-empty'}`} title={`${formatDate(day.date)}: ${day.status ?? 'tidak tercatat'}`}>
                                                            {day.status ?? '·'}
                                                        </span>
                                                    ))}
                                                </span>
                                                <span className="risk-rate">
                                                    Kehadiran <b>{student.rate === null ? '—' : `${student.rate}%`}</b> · A {student.counts.A} · S {student.counts.S} · I {student.counts.I}
                                                </span>
                                                <button type="button" className="btn btn-ghost" onClick={() => setOpenStudent(student.id)}>
                                                    Lihat rekap
                                                </button>
                                            </footer>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </section>

                        <aside className="risk-rules">
                            <h4>Cara sistem menilai</h4>
                            <p className="text-muted" style={{ fontSize: 13 }}>
                                Siswa masuk daftar kalau memenuhi salah satu aturan ini. Satu aturan merah sudah cukup untuk "Risiko tinggi".
                            </p>
                            <ul>
                                {rules.map((rule) => (
                                    <li key={rule.text}>
                                        <span className={`tag ${LEVELS[rule.level].className}`}>{rule.level === 'tinggi' ? 'Tinggi' : 'Perhatian'}</span>
                                        {rule.text}
                                    </li>
                                ))}
                            </ul>
                            <p className="text-muted" style={{ fontSize: 13 }}>
                                Kotak kecil di tiap siswa adalah status 10 hari sekolah terakhir, dari kiri (lama) ke kanan (terbaru).
                            </p>
                        </aside>
                    </div>
                </>
            )}

            {openStudent && <StudentRecapModal studentId={openStudent} recapLink={auth.user.role !== 'guru_bk'} onClose={() => setOpenStudent(null)} />}
        </>
    );
}

EarlyWarning.layout = (page) => <AppLayout>{page}</AppLayout>;
