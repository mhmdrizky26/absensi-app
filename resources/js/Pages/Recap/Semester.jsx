import { Head, router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import RecapTabs from '@/Components/RecapTabs';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

export default function SemesterRecap({ academicYear, classrooms, classroom, semester, range, rows, totals }) {
    function visit(changes) {
        router.get('/rekap/semester', { classroom: classroom?.id, semester, ...changes }, { preserveScroll: true });
    }

    if (!classroom) {
        return (
            <>
                <Head title="Rekap semester" />
                <div className="page-head">
                    <h1>Rekap semester</h1>
                </div>
                <div className="empty">
                    <h4>Belum ada kelas</h4>
                    <p className="text-muted">Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif, atau belum ada kelas.</p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Rekap semester ${classroom.name}`} />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        Kehadiran untuk rapor · Semester {semester === 'ganjil' ? 'Ganjil' : 'Genap'} {academicYear}
                    </h6>
                    <h1>Kelas {classroom.name}</h1>
                    {range && (
                        <div className="text-muted" style={{ fontSize: 14, marginTop: 4 }}>
                            {formatDate(range.from)} – {formatDate(range.to)} · Wali kelas: {classroom.homeroomTeacher ?? '—'}
                        </div>
                    )}
                </div>
                <div className="page-actions no-print">
                    <button type="button" className="btn btn-secondary" onClick={() => window.print()}>
                        <Printer size={16} aria-hidden="true" /> Cetak / PDF
                    </button>
                    <a href={`/rekap/ekspor/semester?classroom=${classroom.id}&semester=${semester}`} className="btn btn-primary">
                        <Download size={16} aria-hidden="true" /> Excel untuk rapor
                    </a>
                </div>
            </div>

            <div className="recap-toolbar no-print">
                <RecapTabs current="semester" query={`?classroom=${classroom.id}`} />
                {classrooms.length > 1 && (
                    <select className="input" style={{ width: 'auto' }} value={classroom.id} onChange={(event) => visit({ classroom: event.target.value })} aria-label="Kelas">
                        {classrooms.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                )}
                <div className="seg" role="radiogroup" aria-label="Semester">
                    {[
                        ['ganjil', 'Ganjil'],
                        ['genap', 'Genap'],
                    ].map(([value, label]) => (
                        <label key={value} className="seg-opt">
                            <input type="radio" name="semester" checked={semester === value} onChange={() => visit({ semester: value })} />
                            {label}
                        </label>
                    ))}
                </div>
            </div>

            <div className="table-wrap">
                <table className="table" style={{ minWidth: 640 }}>
                    <thead>
                        <tr>
                            <th style={{ width: 40 }}>No</th>
                            <th>Nama</th>
                            <th className="num">Sakit</th>
                            <th className="num">Izin</th>
                            <th className="num">Alpa</th>
                            <th className="num">Hadir</th>
                            <th className="num">Terlambat</th>
                            <th className="num">Dispensasi</th>
                            <th className="num">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={row.id} style={row.isActive ? undefined : { opacity: 0.55 }}>
                                <td>{index + 1}</td>
                                <td>
                                    <span style={{ fontWeight: 600 }}>{row.name}</span>
                                    <span className="cell-sub">
                                        NIS {row.nis}
                                        {row.nisn ? ` · NISN ${row.nisn}` : ''}
                                    </span>
                                </td>
                                <td className="num">{row.counts.S}</td>
                                <td className="num">{row.counts.I}</td>
                                <td className="num" style={row.counts.A >= 5 ? { color: 'var(--color-danger)', fontWeight: 800 } : undefined}>
                                    {row.counts.A}
                                </td>
                                <td className="num">{row.counts.H}</td>
                                <td className="num">{row.counts.T}</td>
                                <td className="num">{row.counts.D}</td>
                                <td className={`num ${row.rate !== null && row.rate < 80 ? 'is-low' : ''}`}>{row.rate === null ? '—' : `${row.rate}%`}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colSpan={2}>Jumlah kelas</th>
                            <th className="num">{totals.S}</th>
                            <th className="num">{totals.I}</th>
                            <th className="num">{totals.A}</th>
                            <th className="num">{totals.H}</th>
                            <th className="num">{totals.T}</th>
                            <th className="num">{totals.D}</th>
                            <th />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </>
    );
}

SemesterRecap.layout = (page) => <AppLayout>{page}</AppLayout>;
