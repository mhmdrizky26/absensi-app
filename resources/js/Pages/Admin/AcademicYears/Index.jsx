import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

/**
 * Isian awal untuk tahun ajaran baru: satu tahun setelah yang terbaru.
 */
function suggestNext(academicYears) {
    const latest = academicYears[0]?.name ?? `${new Date().getFullYear() - 1}/${new Date().getFullYear()}`;
    const start = Number(latest.split('/')[1]);

    return {
        name: `${start}/${start + 1}`,
        odd_semester_starts_on: `${start}-07-13`,
        even_semester_starts_on: `${start + 1}-01-04`,
        ends_on: `${start + 1}-06-26`,
    };
}

export default function AcademicYearsIndex({ academicYears }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const form = useForm({ name: '', odd_semester_starts_on: '', even_semester_starts_on: '', ends_on: '' });

    function openCreate() {
        form.clearErrors();
        form.setData(suggestNext(academicYears));
        setEditing('new');
    }

    function openEdit(academicYear) {
        form.clearErrors();
        form.setData({
            name: academicYear.name,
            odd_semester_starts_on: academicYear.oddSemesterStartsOn,
            even_semester_starts_on: academicYear.evenSemesterStartsOn,
            ends_on: academicYear.endsOn,
        });
        setEditing(academicYear);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/tahun-ajaran', options);
        } else {
            form.put(`/tahun-ajaran/${editing.id}`, options);
        }
    }

    function activate(academicYear) {
        router.post(`/tahun-ajaran/${academicYear.id}/aktifkan`, {}, { preserveScroll: true });
    }

    function destroy() {
        router.delete(`/tahun-ajaran/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    return (
        <>
            <Head title="Tahun ajaran" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Master data</h6>
                    <h1>Tahun ajaran</h1>
                </div>
                <button type="button" className="btn btn-primary" onClick={openCreate}>
                    <Plus size={16} aria-hidden="true" /> Tambah tahun ajaran
                </button>
            </div>

            <p className="text-muted" style={{ maxWidth: 640 }}>
                Hanya satu tahun ajaran yang aktif. Kelas, siswa, dan absensi selalu mengikuti tahun ajaran aktif. Tanggal semester dipakai untuk rekap per semester. Saat tahun ajaran baru dijadikan aktif, muncul layar kenaikan kelas: semua siswa naik satu tingkat otomatis dan siswa kelas IX dihapus.
            </p>

            {academicYears.length === 0 ? (
                <div className="empty">
                    <h4>Belum ada tahun ajaran</h4>
                    <p className="text-muted">Tambahkan tahun ajaran pertama. Tahun ajaran pertama otomatis menjadi aktif.</p>
                </div>
            ) : (
                <div className="table-wrap">
                    <table className="table" style={{ minWidth: 720 }}>
                        <thead>
                            <tr>
                                <th>Tahun ajaran</th>
                                <th>Semester ganjil</th>
                                <th>Semester genap</th>
                                <th>Selesai</th>
                                <th className="num">Kelas</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {academicYears.map((academicYear) => (
                                <tr key={academicYear.id}>
                                    <td style={{ fontWeight: 800 }}>{academicYear.name}</td>
                                    <td>{formatDate(academicYear.oddSemesterStartsOn)}</td>
                                    <td>{formatDate(academicYear.evenSemesterStartsOn)}</td>
                                    <td>{formatDate(academicYear.endsOn)}</td>
                                    <td className="num">{academicYear.classroomsCount}</td>
                                    <td>
                                        {academicYear.isActive ? (
                                            <span className="tag tag-accent">Aktif</span>
                                        ) : (
                                            <button type="button" className="btn btn-ghost" onClick={() => activate(academicYear)}>
                                                Jadikan aktif
                                            </button>
                                        )}
                                    </td>
                                    <td className="cell-actions">
                                        <button type="button" className="btn btn-ghost" onClick={() => openEdit(academicYear)}>
                                            Ubah
                                        </button>
                                        {!academicYear.isActive && (
                                            <button type="button" className="btn btn-ghost" onClick={() => setDeleting(academicYear)}>
                                                Hapus
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Modal open={editing !== null} title={editing === 'new' ? 'Tambah tahun ajaran' : `Ubah ${editing?.name}`} onClose={() => setEditing(null)}>
                <form onSubmit={submit} noValidate>
                    <Field label="Tahun ajaran" htmlFor="name" error={form.errors.name} hint="Contoh: 2026/2027">
                        <input id="name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>
                    <div className="form-grid">
                        <Field label="Awal semester ganjil" htmlFor="odd" error={form.errors.odd_semester_starts_on}>
                            <input id="odd" type="date" className="input" value={form.data.odd_semester_starts_on} onChange={(event) => form.setData('odd_semester_starts_on', event.target.value)} />
                        </Field>
                        <Field label="Awal semester genap" htmlFor="even" error={form.errors.even_semester_starts_on}>
                            <input id="even" type="date" className="input" value={form.data.even_semester_starts_on} onChange={(event) => form.setData('even_semester_starts_on', event.target.value)} />
                        </Field>
                        <Field label="Akhir tahun ajaran" htmlFor="ends" error={form.errors.ends_on}>
                            <input id="ends" type="date" className="input" value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} />
                        </Field>
                    </div>
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog open={deleting !== null} title={`Hapus tahun ajaran ${deleting?.name}?`} confirmLabel="Hapus" onConfirm={destroy} onClose={() => setDeleting(null)}>
                Tahun ajaran yang masih memiliki kelas tidak bisa dihapus.
            </ConfirmDialog>
        </>
    );
}

AcademicYearsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
