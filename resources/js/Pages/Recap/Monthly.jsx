import { Head, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, Paperclip, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import RecapTabs from '@/Components/RecapTabs';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { getJson } from '@/lib/http';

const STATUSES = [
    ['H', 'Hadir'],
    ['T', 'Terlambat'],
    ['D', 'Dispensasi'],
    ['S', 'Sakit'],
    ['I', 'Izin'],
    ['A', 'Alpa'],
];
const WEEKDAY_INITIALS = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];

const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const parseDate = (value) => new Date(`${value}T00:00:00`);

function shiftMonth(month, delta) {
    const [year, number] = month.split('-').map(Number);
    const date = new Date(year, number - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function CorrectionDialog({ cell, onClose }) {
    const [history, setHistory] = useState(null);
    const form = useForm({ student_id: cell.student.id, date: cell.date, status: cell.status && cell.status !== 'D' ? cell.status : 'H', reason: '' });

    useEffect(() => {
        getJson(`/rekap/riwayat?student_id=${cell.student.id}&date=${cell.date}`)
            .then(setHistory)
            .catch(() => setHistory({ mark: null, logs: [] }));
    }, [cell]);

    function submit(event) {
        event.preventDefault();
        form.post('/rekap/koreksi', { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal open title={cell.student.name} onClose={onClose} width={520}>
            <div className="text-muted" style={{ marginTop: -8 }}>
                {dayFormatter.format(parseDate(cell.date))} · NIS {cell.student.nis}
            </div>

            <div className="history">
                {history === null && <p className="text-muted">Memuat riwayat…</p>}
                {history && !history.mark && <p className="text-muted">Belum ada catatan untuk hari ini (kelas tidak di-scan).</p>}
                {history?.mark && (
                    <p style={{ margin: 0 }}>
                        Sekarang <b>{history.mark.statusLabel}</b> · {history.mark.source}
                        {history.mark.recorder ? ` oleh ${history.mark.recorder}` : ''}, {formatDateTime(history.mark.recordedAt)}
                        {history.mark.note ? <span className="cell-sub">“{history.mark.note}”</span> : null}
                        {history.mark.hasAttachment && (
                            <a href={`/surat/${history.mark.id}`} target="_blank" rel="noreferrer" className="cell-sub">
                                <Paperclip size={12} aria-hidden="true" /> Lihat surat
                            </a>
                        )}
                    </p>
                )}
                {history?.logs.length > 0 && (
                    <ul className="history-list">
                        {history.logs.map((log, index) => (
                            <li key={index}>
                                {formatDateTime(log.at)}: {log.from ?? 'kosong'} → <b>{log.to}</b>
                                {log.by ? ` oleh ${log.by}` : ''} ({log.source}){log.reason ? ` — ${log.reason}` : ''}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <form onSubmit={submit} noValidate>
                <Field label="Ubah menjadi" error={form.errors.status || form.errors.student_id || form.errors.date}>
                    <div className="seg">
                        {STATUSES.filter(([value]) => value !== 'D').map(([value, label]) => (
                            <label key={value} className="seg-opt">
                                <input type="radio" name="status" checked={form.data.status === value} onChange={() => form.setData('status', value)} />
                                {label}
                            </label>
                        ))}
                    </div>
                </Field>
                <Field label="Alasan koreksi" htmlFor="reason" error={form.errors.reason} hint='Contoh: "ada surat sakit", "lupa di-scan, siswa hadir"'>
                    <input id="reason" className="input" maxLength={200} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                </Field>
                <div className="dialog-actions">
                    <button type="button" className="btn btn-secondary" onClick={onClose}>
                        Batal
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={form.processing}>
                        Simpan koreksi
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function MonthlyRecap({ classrooms, classroom, month, today, grid }) {
    const [cell, setCell] = useState(null);

    function visit(changes) {
        router.get('/rekap', { classroom: classroom?.id, bulan: month, ...changes }, { preserveScroll: true });
    }

    if (!classroom) {
        return (
            <>
                <Head title="Rekap" />
                <div className="page-head">
                    <h1>Rekap absensi</h1>
                </div>
                <div className="empty">
                    <h4>Belum ada kelas</h4>
                    <p className="text-muted">Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif, atau belum ada kelas. Hubungi admin.</p>
                </div>
            </>
        );
    }

    const query = `?classroom=${classroom.id}`;
    const monthLabel = monthFormatter.format(parseDate(`${month}-01`));

    return (
        <>
            <Head title={`Rekap ${classroom.name} · ${monthLabel}`} />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Rekap absensi · {classroom.homeroomTeacher ?? 'Wali kelas belum ditentukan'}</h6>
                    <h1>Kelas {classroom.name}</h1>
                </div>
                <div className="page-actions no-print">
                    <button type="button" className="btn btn-secondary" onClick={() => window.print()}>
                        <Printer size={16} aria-hidden="true" /> Cetak
                    </button>
                    <a href={`/rekap/ekspor/bulanan?classroom=${classroom.id}&bulan=${month}`} className="btn btn-secondary">
                        <Download size={16} aria-hidden="true" /> Excel
                    </a>
                </div>
            </div>

            <div className="recap-toolbar no-print">
                <RecapTabs current="monthly" query={query} />
                {classrooms.length > 1 && (
                    <select className="input" style={{ width: 'auto' }} value={classroom.id} onChange={(event) => visit({ classroom: event.target.value })} aria-label="Kelas">
                        {classrooms.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                )}
                <div className="month-nav">
                    <button type="button" className="btn btn-icon btn-secondary" aria-label="Bulan sebelumnya" onClick={() => visit({ bulan: shiftMonth(month, -1) })}>
                        <ChevronLeft size={16} aria-hidden="true" />
                    </button>
                    <span>{monthLabel}</span>
                    <button type="button" className="btn btn-icon btn-secondary" aria-label="Bulan berikutnya" onClick={() => visit({ bulan: shiftMonth(month, 1) })}>
                        <ChevronRight size={16} aria-hidden="true" />
                    </button>
                </div>
                <div className="legend">
                    {STATUSES.map(([value, label]) => (
                        <span key={value}>
                            <span className={`mark mark-${value}`}>{value}</span> {label}
                        </span>
                    ))}
                </div>
            </div>

            <p className="text-muted no-print" style={{ fontSize: 13 }}>
                Klik sel untuk melihat riwayat atau mengoreksi. Kehadiran = (Hadir + Terlambat + Dispensasi) ÷ semua hari yang tercatat. Dispensasi hanya lewat menu Dispensasi.
            </p>

            {grid.days.length === 0 ? (
                <div className="empty">
                    <h4>Tidak ada hari sekolah di bulan ini</h4>
                </div>
            ) : (
                <div className="recap-grid-wrap">
                    <table className="recap-grid">
                        <thead>
                            <tr>
                                <th className="sticky-col">Siswa</th>
                                {grid.days.map((day) => {
                                    const date = parseDate(day);

                                    return (
                                        <th key={day} className={day > today ? 'is-future' : undefined}>
                                            <span className="day-initial">{WEEKDAY_INITIALS[date.getDay()]}</span>
                                            {date.getDate()}
                                        </th>
                                    );
                                })}
                                {STATUSES.map(([value]) => (
                                    <th key={value} className="total-col">
                                        {value}
                                    </th>
                                ))}
                                <th className="total-col">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {grid.students.map((student) => (
                                <tr key={student.id} className={student.isActive ? undefined : 'is-inactive'}>
                                    <th className="sticky-col" scope="row">
                                        {student.name}
                                        {!student.isActive && <span className="cell-sub">tidak aktif</span>}
                                    </th>
                                    {grid.days.map((day) => {
                                        const mark = student.marks[day];
                                        const isFuture = day > today;

                                        return (
                                            <td key={day} className={isFuture ? 'is-future' : undefined}>
                                                <button
                                                    type="button"
                                                    className={`mark ${mark ? `mark-${mark.status}` : 'mark-empty'}`}
                                                    disabled={isFuture}
                                                    aria-label={`${student.name}, ${day}: ${mark ? mark.status : 'kosong'}`}
                                                    onClick={() => setCell({ student, date: day, status: mark?.status })}
                                                >
                                                    {mark ? mark.status : isFuture ? '' : '·'}
                                                </button>
                                            </td>
                                        );
                                    })}
                                    {STATUSES.map(([value]) => (
                                        <td key={value} className="total-col">
                                            {student.counts[value] || ''}
                                        </td>
                                    ))}
                                    <td className={`total-col ${student.rate !== null && student.rate < 80 ? 'is-low' : ''}`}>{student.rate === null ? '—' : `${student.rate}%`}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th className="sticky-col">Hadir per hari</th>
                                {grid.days.map((day) => (
                                    <td key={day} className="daily-rate">
                                        {grid.dailyRates[day] ?? ''}
                                    </td>
                                ))}
                                <td colSpan={STATUSES.length + 1} />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}

            {cell && <CorrectionDialog cell={cell} onClose={() => setCell(null)} />}
        </>
    );
}

MonthlyRecap.layout = (page) => <AppLayout>{page}</AppLayout>;
