import { Head, Link, router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import RecapTabs from '@/Components/RecapTabs';
import AppLayout from '@/Layouts/AppLayout';

const STATUSES = [
    ['H', 'Hadir'],
    ['T', 'Terlambat'],
    ['D', 'Dispensasi'],
    ['S', 'Sakit'],
    ['I', 'Izin'],
    ['A', 'Alpa'],
];

export default function SchoolRecap({ academicYear, period, month, semester, label, rows, totals, rate }) {
    const params = period === 'semester' ? { jenis: 'semester', semester } : { jenis: 'bulan', bulan: month };

    function visit(changes) {
        router.get('/rekap/sekolah', { ...params, ...changes }, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Rekap sekolah · ${label}`} />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Rekap sekolah · Tahun ajaran {academicYear ?? '—'}</h6>
                    <h1>{label}</h1>
                </div>
                <div className="page-actions no-print">
                    <button type="button" className="btn btn-secondary" onClick={() => window.print()}>
                        <Printer size={16} aria-hidden="true" /> Cetak
                    </button>
                    <a href={`/rekap/ekspor/sekolah?${new URLSearchParams(params)}`} className="btn btn-secondary">
                        <Download size={16} aria-hidden="true" /> Excel
                    </a>
                </div>
            </div>

            <div className="recap-toolbar no-print">
                <RecapTabs current="school" />
                <div className="seg" role="radiogroup" aria-label="Periode">
                    <label className="seg-opt">
                        <input type="radio" name="period" checked={period === 'bulan'} onChange={() => visit({ jenis: 'bulan', bulan: month })} />
                        Per bulan
                    </label>
                    <label className="seg-opt">
                        <input type="radio" name="period" checked={period === 'semester'} onChange={() => visit({ jenis: 'semester', semester })} />
                        Per semester
                    </label>
                </div>
                {period === 'bulan' ? (
                    <input type="month" className="input" style={{ width: 'auto' }} value={month} onChange={(event) => event.target.value && visit({ bulan: event.target.value })} aria-label="Bulan" />
                ) : (
                    <select className="input" style={{ width: 'auto' }} value={semester} onChange={(event) => visit({ semester: event.target.value })} aria-label="Semester">
                        <option value="ganjil">Semester Ganjil</option>
                        <option value="genap">Semester Genap</option>
                    </select>
                )}
            </div>

            <div className="stat-strip">
                {STATUSES.map(([status, statusLabel]) => (
                    <div key={status} className="stat">
                        <div className="stat-label">{statusLabel}</div>
                        <div className="stat-value">{totals[status].toLocaleString('id-ID')}</div>
                    </div>
                ))}
                <div className="stat">
                    <div className="stat-label">Kehadiran</div>
                    <div className="stat-value">{rate === null ? '—' : `${rate}%`}</div>
                </div>
            </div>

            <div className="table-wrap">
                <table className="table" style={{ minWidth: 720 }}>
                    <thead>
                        <tr>
                            <th style={{ width: 80 }}>Kelas</th>
                            <th>Wali kelas</th>
                            <th className="num">Siswa</th>
                            {STATUSES.map(([status]) => (
                                <th key={status} className="num">
                                    {status}
                                </th>
                            ))}
                            <th style={{ width: '22%' }}>Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td style={{ fontWeight: 800 }}>
                                    <Link href={`/rekap?classroom=${row.id}`}>{row.name}</Link>
                                </td>
                                <td>{row.homeroomTeacher ?? <span className="text-muted">—</span>}</td>
                                <td className="num">{row.students}</td>
                                {STATUSES.map(([status]) => (
                                    <td key={status} className="num" style={status === 'A' && row.counts.A > 0 ? { color: 'var(--color-danger)' } : undefined}>
                                        {row.counts[status]}
                                    </td>
                                ))}
                                <td>
                                    <div className="bar-cell">
                                        <div className="bar">
                                            <div style={{ width: `${row.rate ?? 0}%` }} />
                                        </div>
                                        <span className={row.rate !== null && row.rate < 90 ? 'is-low' : undefined}>{row.rate === null ? '—' : `${row.rate}%`}</span>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

SchoolRecap.layout = (page) => <AppLayout>{page}</AppLayout>;
