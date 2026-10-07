import { router } from '@inertiajs/react';
import { ChartLegend, ColumnChart, LineChart, StackedColumnChart } from '@/Components/Charts';
import { formatDate } from '@/lib/format';

/** Status selain Hadir, dengan warna grafik yang sudah divalidasi aman untuk buta warna. */
const NON_PRESENT = [
    { key: 'T', label: 'Terlambat', color: 'var(--chart-T)' },
    { key: 'D', label: 'Dispensasi', color: 'var(--chart-D)' },
    { key: 'S', label: 'Sakit', color: 'var(--chart-S)' },
    { key: 'I', label: 'Izin', color: 'var(--chart-I)' },
    { key: 'A', label: 'Alpa', color: 'var(--chart-A)' },
];

const total = (counts) => Object.values(counts).reduce((sum, count) => sum + count, 0);
const share = (counts, status) => (total(counts) ? Math.round((counts[status] / total(counts)) * 1000) / 10 : 0);

/** Kunci titik terakhir dan titik terendah, untuk label nilai yang selektif. */
function highlightKeys(points) {
    const recorded = points.filter((point) => point.value !== null);

    if (recorded.length === 0) {
        return [];
    }

    const lowest = recorded.reduce((low, point) => (point.value < low.value ? point : low));

    return [...new Set([recorded[recorded.length - 1].key, lowest.key])];
}

function ChartCard({ title, subtitle, children, table }) {
    return (
        <section className="chart-card">
            <h4 style={{ margin: 0 }}>{title}</h4>
            {subtitle && <p className="text-muted chart-subtitle">{subtitle}</p>}
            {children}
            {table && (
                <details className="chart-table">
                    <summary>Lihat tabel</summary>
                    {table}
                </details>
            )}
        </section>
    );
}

function CountsTable({ rows, firstColumn }) {
    return (
        <div className="table-wrap">
            <table className="table">
                <thead>
                    <tr>
                        <th>{firstColumn}</th>
                        {['H', 'T', 'D', 'S', 'I', 'A'].map((status) => (
                            <th key={status} className="num">
                                {status}
                            </th>
                        ))}
                        <th className="num">%</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.key}>
                            <td>{row.label}</td>
                            {['H', 'T', 'D', 'S', 'I', 'A'].map((status) => (
                                <td key={status} className="num">
                                    {row.counts[status].toLocaleString('id-ID')}
                                </td>
                            ))}
                            <td className="num">{row.rate === null ? '—' : `${row.rate}%`}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Tren kehadiran di dasbor: seluruh sekolah (admin, guru BK) atau kelas sendiri (wali kelas).
 */
export default function TrendPanel({ scopeLabel, classrooms, filters, homeroom, isHomeroomTeacher, period, summary, monthly, weekdays, daily, classroomRanking }) {
    function applyFilters(changes) {
        router.get('/dasbor', { ...filters, ...changes }, { only: ['trends'], preserveState: true, preserveScroll: true, replace: true });
    }

    const monthlyRates = monthly.map((month) => ({ key: month.key, label: month.label, value: month.rate }));
    const dailyRates = daily.map((day) => ({ key: day.key, label: day.label, value: day.rate }));
    const weekdayAbsence = weekdays.map((day) => ({ key: day.key, label: day.label, value: day.absentRate, counts: day.counts }));
    const absenceShares = monthly.map((month) => ({
        key: month.key,
        label: month.label,
        values: Object.fromEntries(NON_PRESENT.map((status) => [status.key, share(month.counts, status.key)])),
    }));
    const delta = summary?.thisMonth && summary?.lastMonth ? summary.thisMonth.rate - summary.lastMonth.rate : null;

    return (
        <>
            <div className="page-head trend-head">
                <div>
                    <h6 className="text-muted">Tren kehadiran{period ? ` · ${period.label}` : ''}</h6>
                    <h2 style={{ margin: 0 }}>{scopeLabel}</h2>
                </div>
                <div className="page-actions">
                    {classrooms.length > 0 && (
                        <select className="input" style={{ width: 'auto' }} aria-label="Kelas" value={filters.classroom ?? ''} onChange={(event) => applyFilters({ classroom: event.target.value || undefined })}>
                            <option value="">Seluruh sekolah</option>
                            {classrooms.map((classroom) => (
                                <option key={classroom.id} value={classroom.id}>
                                    Kelas {classroom.name}
                                </option>
                            ))}
                        </select>
                    )}
                    <div className="seg" role="radiogroup" aria-label="Periode">
                        {[
                            ['semester', 'Semester ini'],
                            ['tahun', 'Tahun ajaran'],
                        ].map(([value, text]) => (
                            <label key={value} className="seg-opt">
                                <input type="radio" name="periode" checked={filters.periode === value} onChange={() => applyFilters({ periode: value })} />
                                {text}
                            </label>
                        ))}
                    </div>
                </div>
            </div>

            {!summary ? (
                <div className="empty">
                    <h4>Belum ada data</h4>
                    <p className="text-muted">{isHomeroomTeacher && !homeroom ? 'Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif. Hubungi admin.' : 'Belum ada tahun ajaran aktif atau kelas.'}</p>
                </div>
            ) : (
                <>
                    <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                        Data {formatDate(period.from)} s.d. {formatDate(period.to)}. Kehadiran = (Hadir + Terlambat + Dispensasi) ÷ semua hari yang tercatat. Arahkan kursor atau ketuk grafik untuk melihat angkanya.
                    </p>

                    <div className="stat-strip">
                        <div className="stat">
                            <div className="stat-label">Kehadiran periode ini</div>
                            <div className="stat-value">{summary.rate === null ? '—' : `${summary.rate}%`}</div>
                        </div>
                        <div className="stat">
                            <div className="stat-label">{summary.thisMonth ? `Bulan ${summary.thisMonth.label}` : 'Bulan ini'}</div>
                            <div className="stat-value">{summary.thisMonth?.rate == null ? '—' : `${summary.thisMonth.rate}%`}</div>
                            {delta !== null && (
                                <div className={`stat-delta ${delta < 0 ? 'is-down' : 'is-up'}`}>
                                    {delta > 0 ? '+' : ''}
                                    {delta} poin dari {summary.lastMonth.label}
                                </div>
                            )}
                        </div>
                        <div className="stat status-A">
                            <div className="stat-label">Total alpa</div>
                            <div className="stat-value">{summary.counts.A.toLocaleString('id-ID')}</div>
                        </div>
                        <div className="stat">
                            <div className="stat-label">Hari paling sering alpa</div>
                            <div className="stat-value">{summary.worstWeekday?.absentRate ? summary.worstWeekday.label : '—'}</div>
                            {summary.worstWeekday?.absentRate > 0 && <div className="stat-delta">{summary.worstWeekday.absentRate}% catatan hari itu alpa</div>}
                        </div>
                    </div>

                    <div className="chart-grid-layout">
                        <ChartCard title="Kehadiran per bulan" subtitle="Seluruh tahun ajaran sampai hari ini." table={<CountsTable rows={monthly} firstColumn="Bulan" />}>
                            <ColumnChart data={monthlyRates} label="Kehadiran" highlight={highlightKeys(monthlyRates)} />
                        </ChartCard>

                        <ChartCard title="Selain hadir per bulan" subtitle="Persen dari semua catatan bulan itu." table={<CountsTable rows={monthly} firstColumn="Bulan" />}>
                            <ChartLegend series={NON_PRESENT} />
                            <StackedColumnChart data={absenceShares} series={NON_PRESENT} label="Komposisi status selain hadir per bulan" />
                        </ChartCard>

                        <ChartCard title="Kehadiran harian" subtitle={`${daily.length} hari sekolah terakhir.`} table={<CountsTable rows={daily} firstColumn="Tanggal" />}>
                            <LineChart data={dailyRates} label="Kehadiran" highlight={highlightKeys(dailyRates)} />
                        </ChartCard>

                        <ChartCard title="Alpa per hari dalam seminggu" subtitle="Persen catatan yang alpa di tiap hari, dalam periode yang dipilih." table={<CountsTable rows={weekdays} firstColumn="Hari" />}>
                            <ColumnChart
                                data={weekdayAbsence}
                                label="Alpa"
                                color="var(--chart-A)"
                                max={Math.max(8, ...weekdayAbsence.map((day) => Math.ceil(((day.value ?? 0) + 1) / 4) * 4))}
                                highlight={summary.worstWeekday ? [summary.worstWeekday.key] : []}
                                tipRows={(day) => [
                                    { label: 'Alpa', value: day.value === null ? '—' : `${day.value}%` },
                                    { label: 'Jumlah alpa', value: day.counts.A.toLocaleString('id-ID') },
                                ]}
                            />
                        </ChartCard>
                    </div>

                    {classroomRanking.length > 0 && (
                        <section className="chart-card" style={{ marginTop: 32 }}>
                            <h4 style={{ margin: 0 }}>Perbandingan kelas</h4>
                            <p className="text-muted chart-subtitle">Kehadiran tiap kelas dalam periode yang dipilih, dari yang terendah. Klik kelas untuk melihat trennya.</p>
                            <div className="rank-list">
                                {classroomRanking.map((row) => (
                                    <button key={row.id} type="button" className="rank-row" onClick={() => applyFilters({ classroom: row.id })}>
                                        <span className="rank-name">
                                            {row.name}
                                            <span className="cell-sub">{row.homeroomTeacher ?? 'Belum ada wali kelas'}</span>
                                        </span>
                                        <span className="rank-bar">
                                            <span style={{ width: `${row.rate ?? 0}%` }} />
                                        </span>
                                        <span className="rank-value">{row.rate === null ? '—' : `${row.rate}%`}</span>
                                        <span className="rank-absent">{row.counts.A} alpa</span>
                                    </button>
                                ))}
                            </div>
                        </section>
                    )}
                </>
            )}
        </>
    );
}
