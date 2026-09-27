import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, Download } from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import AppLayout from '@/Layouts/AppLayout';

const CHOICES = {
    normal: null,
    repeat: 'Tinggal kelas',
    leave: 'Pindah sekolah',
};

export default function Promotion({ source, target, classrooms }) {
    const [choices, setChoices] = useState({});
    const [query, setQuery] = useState('');
    const [skipOpen, setSkipOpen] = useState(false);
    const form = useForm({ repeaters: [], leavers: [], confirm_delete: false });

    const summary = useMemo(() => {
        const counts = { promoted: 0, repeated: 0, left: 0, deleted: 0 };
        const targetNames = new Set();

        for (const classroom of classrooms) {
            targetNames.add(classroom.name);

            if (classroom.target) {
                targetNames.add(classroom.target);
            }

            for (const student of classroom.students) {
                const choice = choices[student.id];

                if (choice === 'repeat') {
                    counts.repeated++;
                } else if (choice === 'leave') {
                    counts.left++;
                } else if (classroom.target) {
                    counts.promoted++;
                } else {
                    counts.deleted++;
                }
            }
        }

        return { ...counts, classrooms: targetNames.size };
    }, [classrooms, choices]);

    function choose(studentId, choice) {
        setChoices((current) => {
            const next = { ...current };

            if (choice === 'normal') {
                delete next[studentId];
            } else {
                next[studentId] = choice;
            }

            return next;
        });
    }

    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            repeaters: Object.keys(choices).filter((id) => choices[id] === 'repeat').map(Number),
            leavers: Object.keys(choices).filter((id) => choices[id] === 'leave').map(Number),
        }));
        form.post(`/tahun-ajaran/${target.id}/kenaikan-kelas`);
    }

    const search = query.trim().toLowerCase();

    return (
        <>
            <Head title="Kenaikan kelas" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        <Link href="/tahun-ajaran">Tahun ajaran</Link> · Kenaikan kelas
                    </h6>
                    <h1>
                        {source.name} <ArrowRight size={32} aria-hidden="true" style={{ verticalAlign: 'middle' }} /> {target.name}
                    </h1>
                </div>
            </div>

            <p className="text-muted" style={{ maxWidth: 820 }}>
                Sebelum tahun ajaran {target.name} diaktifkan, siswa dinaikkan otomatis: kelas yang sama dibuat dengan <b>kode kelas baru</b> dan <b>tanpa wali kelas</b>, setiap siswa naik satu tingkat di rombel yang sama (VII-A → VIII-A). Tandai siswa yang tinggal kelas atau pindah sekolah di bawah.
            </p>

            <div className="stat-strip">
                <div className="stat">
                    <div className="stat-label">Kelas dibuat</div>
                    <div className="stat-value">{summary.classrooms}</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Naik kelas</div>
                    <div className="stat-value">{summary.promoted}</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Tinggal kelas</div>
                    <div className="stat-value">{summary.repeated}</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Pindah sekolah</div>
                    <div className="stat-value">{summary.left}</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Kelas IX dihapus</div>
                    <div className="stat-value" style={summary.deleted > 0 ? { color: 'var(--color-danger)' } : undefined}>
                        {summary.deleted}
                    </div>
                </div>
            </div>

            {summary.deleted > 0 && (
                <div className="danger-box" role="alert">
                    <div>
                        <b>{summary.deleted} siswa kelas IX akan dihapus permanen</b>, termasuk seluruh riwayat absensi, surat izin, dan dispensasinya. Rekap kelas IX tahun {source.name} juga ikut kosong. Tindakan ini tidak bisa dibatalkan.
                    </div>
                    <a href={`/tahun-ajaran/${target.id}/kenaikan-kelas/arsip`} className="btn btn-secondary">
                        <Download size={16} aria-hidden="true" /> Unduh arsip kelas IX dulu (Excel)
                    </a>
                </div>
            )}

            <div className="toolbar" style={{ paddingTop: 16 }}>
                <div className="field field-grow">
                    <label htmlFor="q">Cari siswa yang tinggal kelas atau pindah</label>
                    <input id="q" type="search" className="input" placeholder="Nama atau NIS" value={query} onChange={(event) => setQuery(event.target.value)} />
                </div>
            </div>

            <div className="promotion-grid">
                {classrooms.map((classroom) => {
                    const students = search ? classroom.students.filter((student) => student.name.toLowerCase().includes(search) || student.nis.includes(search)) : classroom.students;
                    const exceptions = classroom.students.filter((student) => choices[student.id]).length;

                    if (search && students.length === 0) {
                        return null;
                    }

                    return (
                        <details key={classroom.id} className="promotion-class" open={Boolean(search) || undefined}>
                            <summary>
                                <b>{classroom.name}</b>
                                <ArrowRight size={14} aria-hidden="true" />
                                <span className={classroom.target ? undefined : 'is-danger'}>{classroom.target ?? 'Dihapus (lulus)'}</span>
                                <span className="text-muted">
                                    · {classroom.students.length} siswa{exceptions > 0 ? ` · ${exceptions} dikecualikan` : ''}
                                </span>
                            </summary>
                            <div className="promotion-students">
                                {students.map((student) => {
                                    const choice = choices[student.id] ?? 'normal';

                                    return (
                                        <div key={student.id} className="promotion-student">
                                            <span>
                                                {student.name}
                                                <span className="cell-sub">
                                                    NIS {student.nis}
                                                    {CHOICES[choice] ? ` · ${CHOICES[choice]}` : ''}
                                                </span>
                                            </span>
                                            <select className="input" value={choice} onChange={(event) => choose(student.id, event.target.value)} aria-label={`Keputusan untuk ${student.name}`}>
                                                <option value="normal">{classroom.target ? `Naik ke ${classroom.target}` : 'Lulus (dihapus)'}</option>
                                                <option value="repeat">Tinggal di {classroom.name}</option>
                                                <option value="leave">Pindah sekolah</option>
                                            </select>
                                        </div>
                                    );
                                })}
                            </div>
                        </details>
                    );
                })}
            </div>

            <form onSubmit={submit} className="promotion-bar">
                {summary.deleted > 0 && (
                    <label className="radio checkbox">
                        <input type="checkbox" checked={form.data.confirm_delete} onChange={(event) => form.setData('confirm_delete', event.target.checked)} />
                        <span className="dot" />
                        Saya mengerti {summary.deleted} siswa kelas IX akan dihapus permanen.
                    </label>
                )}
                {form.errors.confirm_delete && <div className="field-error">{form.errors.confirm_delete}</div>}
                <div className="page-actions">
                    <button type="button" className="btn btn-ghost" onClick={() => setSkipOpen(true)}>
                        Aktifkan tanpa kenaikan kelas
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={form.processing || (summary.deleted > 0 && !form.data.confirm_delete)}>
                        {form.processing ? 'Memproses…' : `Proses & aktifkan ${target.name}`}
                    </button>
                </div>
            </form>

            <ConfirmDialog
                open={skipOpen}
                title={`Aktifkan ${target.name} tanpa kenaikan kelas?`}
                confirmLabel="Aktifkan saja"
                onConfirm={() => router.post(`/tahun-ajaran/${target.id}/aktifkan`, { without_promotion: true })}
                onClose={() => setSkipOpen(false)}
            >
                Tahun ajaran baru aktif dalam keadaan kosong: kelas dan penempatan siswa harus dibuat sendiri. Kenaikan kelas otomatis tidak bisa dijalankan lagi setelah tahun ajaran ini punya kelas.
            </ConfirmDialog>
        </>
    );
}

Promotion.layout = (page) => <AppLayout>{page}</AppLayout>;
