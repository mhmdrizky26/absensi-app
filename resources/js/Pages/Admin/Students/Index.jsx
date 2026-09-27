import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';

const EMPTY_STUDENT = { nis: '', nisn: '', name: '', gender: 'L', status: 'aktif', classroom_id: '' };

export default function StudentsIndex({ academicYear, students, filters, classrooms, genders, statuses }) {
    const [query, setQuery] = useState(filters.q);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const form = useForm(EMPTY_STUDENT);
    const firstRender = useRef(true);

    function applyFilters(changes) {
        router.get('/siswa', { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    }

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => applyFilters({ q: query }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    function openCreate() {
        form.clearErrors();
        form.setData({ ...EMPTY_STUDENT, classroom_id: /^\d+$/.test(filters.classroom) ? Number(filters.classroom) : '' });
        setEditing('new');
    }

    function openEdit(student) {
        form.clearErrors();
        form.setData({
            nis: student.nis,
            nisn: student.nisn ?? '',
            name: student.name,
            gender: student.gender,
            status: student.status,
            classroom_id: student.classroom?.id ?? '',
        });
        setEditing(student);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/siswa', options);
        } else {
            form.put(`/siswa/${editing.id}`, options);
        }
    }

    function destroy() {
        router.delete(`/siswa/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    return (
        <>
            <Head title="Siswa" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">{academicYear ? `Tahun ajaran ${academicYear.name}` : 'Belum ada tahun ajaran aktif'}</h6>
                    <h1>Siswa</h1>
                </div>
                <div className="page-actions">
                    <Link href="/siswa/import" className="btn btn-secondary">
                        <FileSpreadsheet size={16} aria-hidden="true" /> Import Excel
                    </Link>
                    <button type="button" className="btn btn-primary" onClick={openCreate}>
                        <Plus size={16} aria-hidden="true" /> Tambah siswa
                    </button>
                </div>
            </div>

            <div className="toolbar">
                <div className="field field-grow">
                    <label htmlFor="q">Cari</label>
                    <input id="q" type="search" className="input" placeholder="Nama, NIS, atau NISN" value={query} onChange={(event) => setQuery(event.target.value)} />
                </div>
                <div className="field">
                    <label htmlFor="classroom-filter">Kelas</label>
                    <select id="classroom-filter" className="input" value={filters.classroom} onChange={(event) => applyFilters({ classroom: event.target.value, page: 1 })}>
                        <option value="">Semua kelas</option>
                        <option value="tanpa-kelas">Belum punya kelas</option>
                        {classrooms.map((classroom) => (
                            <option key={classroom.id} value={classroom.id}>
                                {classroom.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="field">
                    <label htmlFor="status-filter">Status</label>
                    <select id="status-filter" className="input" value={filters.status} onChange={(event) => applyFilters({ status: event.target.value, page: 1 })}>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                        <option value="semua">Semua status</option>
                    </select>
                </div>
            </div>

            {students.data.length === 0 ? (
                <div className="empty">
                    <h4>Tidak ada siswa</h4>
                    <p className="text-muted">Tidak ada siswa yang cocok dengan pencarian atau filter ini. Tambahkan siswa satu per satu atau import dari Excel.</p>
                </div>
            ) : (
                <div className="table-wrap">
                    <table className="table" style={{ minWidth: 640 }}>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th style={{ width: 60 }}>JK</th>
                                <th style={{ width: 110 }}>Kelas</th>
                                <th style={{ width: 100 }}>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.map((student) => (
                                <tr key={student.id}>
                                    <td>
                                        <span style={{ fontWeight: 600 }}>{student.name}</span>
                                        <span className="cell-sub">
                                            NIS {student.nis}
                                            {student.nisn ? ` · NISN ${student.nisn}` : ''}
                                        </span>
                                    </td>
                                    <td>{student.gender}</td>
                                    <td style={{ fontWeight: 800 }}>{student.classroom?.name ?? <span className="text-muted" style={{ fontWeight: 400 }}>—</span>}</td>
                                    <td>
                                        <span className={`tag ${student.status === 'aktif' ? 'tag-neutral' : 'tag-outline'}`}>{student.statusLabel}</span>
                                    </td>
                                    <td className="cell-actions">
                                        <button type="button" className="btn btn-ghost" onClick={() => openEdit(student)}>
                                            Ubah
                                        </button>
                                        <button type="button" className="btn btn-ghost" onClick={() => setDeleting(student)}>
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Pagination paginator={students} />

            <Modal open={editing !== null} title={editing === 'new' ? 'Tambah siswa' : `Ubah data ${editing?.name}`} onClose={() => setEditing(null)}>
                <form onSubmit={submit} noValidate>
                    <Field label="Nama lengkap" htmlFor="name" error={form.errors.name}>
                        <input id="name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>
                    <div className="form-grid">
                        <Field label="NIS" htmlFor="nis" error={form.errors.nis}>
                            <input id="nis" className="input" inputMode="numeric" value={form.data.nis} onChange={(event) => form.setData('nis', event.target.value)} />
                        </Field>
                        <Field label="NISN (opsional)" htmlFor="nisn" error={form.errors.nisn}>
                            <input id="nisn" className="input" inputMode="numeric" maxLength={10} value={form.data.nisn} onChange={(event) => form.setData('nisn', event.target.value)} />
                        </Field>
                    </div>
                    <div className="form-grid">
                        <Field label="Jenis kelamin" htmlFor="gender" error={form.errors.gender}>
                            <select id="gender" className="input" value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)}>
                                {genders.map((gender) => (
                                    <option key={gender.value} value={gender.value}>
                                        {gender.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Kelas" htmlFor="classroom" error={form.errors.classroom_id}>
                            <select id="classroom" className="input" value={form.data.classroom_id} onChange={(event) => form.setData('classroom_id', event.target.value)} disabled={!academicYear}>
                                <option value="">— Tanpa kelas —</option>
                                {classrooms.map((classroom) => (
                                    <option key={classroom.id} value={classroom.id}>
                                        {classroom.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Status" htmlFor="status" error={form.errors.status}>
                            <select id="status" className="input" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </select>
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

            <ConfirmDialog open={deleting !== null} title={`Hapus ${deleting?.name}?`} confirmLabel="Hapus" onConfirm={destroy} onClose={() => setDeleting(null)}>
                Data siswa akan dihapus permanen. Untuk siswa yang pindah sekolah atau lulus, sebaiknya ubah statusnya saja supaya riwayat absensinya tetap tersimpan.
            </ConfirmDialog>
        </>
    );
}

StudentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
