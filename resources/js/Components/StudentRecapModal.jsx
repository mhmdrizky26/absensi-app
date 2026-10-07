import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import { formatDate } from '@/lib/format';
import { getJson } from '@/lib/http';

const STATUSES = [
    ['H', 'Hadir'],
    ['T', 'Terlambat'],
    ['D', 'Dispensasi'],
    ['S', 'Sakit'],
    ['I', 'Izin'],
    ['A', 'Alpa'],
];

/**
 * Rekap semester satu siswa: jumlah per status, tanda tiap hari sekolah per
 * bulan, dan catatan alasannya.
 */
export default function StudentRecapModal({ studentId, onClose, recapLink }) {
    const [recap, setRecap] = useState(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        getJson(`/siswa-berisiko/${studentId}`)
            .then(setRecap)
            .catch(() => setFailed(true));
    }, [studentId]);

    return (
        <Modal open title={recap ? recap.name : 'Rekap siswa'} onClose={onClose} width={640}>
            {failed && <p className="alert alert-error">Rekap gagal dimuat. Tutup lalu coba lagi.</p>}
            {!recap && !failed && <p className="text-muted">Memuat…</p>}

            {recap && (
                <div className="student-recap">
                    <p className="text-muted" style={{ margin: 0, fontSize: 14 }}>
                        Kelas {recap.classroom.name} · NIS {recap.nis} · {recap.period}
                    </p>

                    <div className="student-recap-totals">
                        <div className="student-recap-rate">
                            <span className="stat-label">Kehadiran</span>
                            <b>{recap.rate === null ? '—' : `${recap.rate}%`}</b>
                        </div>
                        {STATUSES.map(([status, label]) => (
                            <div key={status} className="student-recap-count">
                                <span className={`mark mark-${status}`} aria-hidden="true">
                                    {status}
                                </span>
                                <span>
                                    {label}
                                    <b>{recap.counts[status]}</b>
                                </span>
                            </div>
                        ))}
                    </div>

                    <div className="student-recap-scroll">
                        {recap.months.map((month) => (
                            <section key={month.label} className="student-recap-month">
                                <h5>
                                    {month.label}
                                    <span className="text-muted">{month.rate === null ? 'belum ada catatan' : `kehadiran ${month.rate}%`}</span>
                                </h5>
                                <div className="student-recap-days">
                                    {month.days.map((day) => (
                                        <span key={day.date} className="student-recap-day" title={`${formatDate(day.date)}: ${day.status ?? 'tidak tercatat'}`}>
                                            <span className="student-recap-date">{day.day}</span>
                                            <span className={`mark ${day.status ? `mark-${day.status}` : 'mark-empty'}`}>{day.status ?? '·'}</span>
                                        </span>
                                    ))}
                                </div>
                            </section>
                        ))}

                        {recap.notes.length > 0 && (
                            <section className="student-recap-month">
                                <h5>Catatan</h5>
                                {recap.notes.map((note) => (
                                    <div key={note.date} className="student-recap-note">
                                        <span className={`tag status-${note.status}`}>{note.statusLabel}</span>
                                        <span className="text-muted">{formatDate(note.date)}</span>
                                        <span>{note.note}</span>
                                    </div>
                                ))}
                            </section>
                        )}
                    </div>
                </div>
            )}

            <div className="dialog-actions">
                {recap && recapLink && (
                    <Link href={`/rekap?classroom=${recap.classroom.id}`} className="btn btn-ghost" style={{ marginRight: 'auto' }}>
                        Buka rekap kelas
                    </Link>
                )}
                <button type="button" className="btn btn-secondary" onClick={onClose}>
                    Tutup
                </button>
            </div>
        </Modal>
    );
}
