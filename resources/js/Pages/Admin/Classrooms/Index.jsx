import { Head, Link, router, useForm } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import AppLayout from '@/Layouts/AppLayout';

export default function ClassroomsIndex({ academicYear, classrooms, teachers, grades }) {
    const [editing, setEditing] = useState(null);
    const [batchOpen, setBatchOpen] = useState(false);
    const [deleting, setDeleting] = useState(null);

    const form = useForm({ grade: 7, section: '', homeroom_teacher_id: '', has_room: true });
    const [lodging, setLodging] = useState(null);
    const [returning, setReturning] = useState(null);
    const batchForm = useForm({ grades: [7, 8, 9], count: 11 });

    if (!academicYear) {
        return (
            <>
                <Head title="Kelas" />
                <div className="page-head">
                    <h1>Kelas</h1>
                </div>
                <div className="empty">
                    <h4>Belum ada tahun ajaran aktif</h4>
                    <p className="text-muted">Kelas selalu terikat ke tahun ajaran. Buat atau aktifkan tahun ajaran terlebih dahulu.</p>
                    <Link href="/tahun-ajaran" className="btn btn-primary">
                        Atur tahun ajaran
                    </Link>
                </div>
            </>
        );
    }

    function openCreate() {
        form.clearErrors();
        form.setData({ grade: 7, section: '', homeroom_teacher_id: '', has_room: true });
        setEditing('new');
    }

    function openEdit(classroom) {
        form.clearErrors();
        form.setData({ grade: classroom.grade, section: classroom.section, homeroom_teacher_id: classroom.homeroomTeacher?.id ?? '', has_room: classroom.hasRoom });
        setEditing(classroom);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/kelas', options);
        } else {
            form.put(`/kelas/${editing.id}`, options);
        }
    }

    function submitBatch(event) {
        event.preventDefault();
        batchForm.post('/kelas/massal', { preserveScroll: true, onSuccess: () => setBatchOpen(false) });
    }

    function toggleBatchGrade(grade) {
        const selected = batchForm.data.grades;
        batchForm.setData('grades', selected.includes(grade) ? selected.filter((value) => value !== grade) : [...selected, grade].sort());
    }

    function destroy() {
        router.delete(`/kelas/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    const editingId = editing === 'new' ? null : editing?.id;
    const totalStudents = classrooms.reduce((sum, classroom) => sum + classroom.studentsCount, 0);

    return (
        <>
            <Head title="Kelas" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Tahun ajaran {academicYear.name}</h6>
                    <h1>Kelas</h1>
                </div>
                <div className="page-actions">
                    <button type="button" className="btn btn-secondary" onClick={() => { batchForm.clearErrors(); setBatchOpen(true); }}>
                        <Layers size={16} aria-hidden="true" /> Buat kelas massal
                    </button>
                    <button type="button" className="btn btn-primary" onClick={openCreate}>
                        <Plus size={16} aria-hidden="true" /> Tambah kelas
                    </button>
                </div>
            </div>

            <p className="text-muted" style={{ maxWidth: 680 }}>
                {classrooms.length} kelas · {totalStudents.toLocaleString('id-ID')} siswa. Siswa absen di tab gerbang; kelas menentukan rekap dan wali kelasnya. Semua guru melihat siapa yang hadir hari ini lewat menu Absen kelas di akunnya. Kelas tanpa ruang sendiri bisa dititipkan ke kelas lain.
            </p>

            {classrooms.length === 0 ? (
                <div className="empty">
                    <h4>Belum ada kelas di tahun ajaran ini</h4>
                    <p className="text-muted">Gunakan "Buat kelas massal" untuk membuat VII-A sampai IX-K sekaligus.</p>
                </div>
            ) : (
                grades.map((grade) => {
                    const rows = classrooms.filter((classroom) => classroom.grade === grade.value);

                    if (rows.length === 0) {
                        return null;
                    }

                    return (
                        <section key={grade.value}>
                            <h4 className="section-title">
                                Kelas {grade.label} <span className="text-muted" style={{ fontSize: 14, fontWeight: 400 }}>{rows.length} rombel</span>
                            </h4>
                            <div className="table-wrap">
                                <table className="table" style={{ minWidth: 640 }}>
                                    <thead>
                                        <tr>
                                            <th style={{ width: 110 }}>Kelas</th>
                                            <th>Wali kelas</th>
                                            <th className="num" style={{ width: 90 }}>Siswa</th>
                                            <th style={{ width: 220 }}>Ruang</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((classroom) => (
                                            <tr key={classroom.id}>
                                                <td style={{ fontWeight: 800 }}>{classroom.name}</td>
                                                <td>{classroom.homeroomTeacher?.name ?? <span className="text-muted">Belum ditentukan</span>}</td>
                                                <td className="num">
                                                    <Link href={`/siswa?classroom=${classroom.id}`}>{classroom.studentsCount}</Link>
                                                    {classroom.guests > 0 && <span className="cell-sub">+{classroom.guests} titipan</span>}
                                                </td>
                                                <td>
                                                    {classroom.hasRoom ? (
                                                        <span className="text-muted">Ruang sendiri</span>
                                                    ) : (
                                                        <>
                                                            <span className="tag tag-warning">Tanpa ruang</span>{' '}
                                                            <button type="button" className="btn btn-ghost" onClick={() => setLodging(classroom)}>
                                                                Titipkan siswa
                                                            </button>
                                                            <button type="button" className="btn btn-ghost" onClick={() => setReturning(classroom)}>
                                                                Kembalikan ke kelas asal
                                                            </button>
                                                            <span className="cell-sub">
                                                                {classroom.lodged}/{classroom.studentsCount} siswa sudah dititipkan
                                                            </span>
                                                        </>
                                                    )}
                                                </td>
                                                <td className="cell-actions">
                                                    <button type="button" className="btn btn-ghost" onClick={() => openEdit(classroom)}>
                                                        Ubah
                                                    </button>
                                                    <button type="button" className="btn btn-ghost" onClick={() => setDeleting(classroom)}>
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    );
                })
            )}

            <Modal open={editing !== null} title={editing === 'new' ? 'Tambah kelas' : `Ubah kelas ${editing?.name}`} onClose={() => setEditing(null)}>
                <form onSubmit={submit} noValidate>
                    <div className="form-grid">
                        <Field label="Tingkat" htmlFor="grade" error={form.errors.grade}>
                            <select id="grade" className="input" value={form.data.grade} onChange={(event) => form.setData('grade', Number(event.target.value))}>
                                {grades.map((grade) => (
                                    <option key={grade.value} value={grade.value}>
                                        {grade.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Rombel" htmlFor="section" error={form.errors.section || form.errors.name} hint="Huruf, misalnya A">
                            <input id="section" className="input" maxLength={2} value={form.data.section} onChange={(event) => form.setData('section', event.target.value.toUpperCase())} />
                        </Field>
                    </div>
                    <Field label="Wali kelas" htmlFor="homeroom" error={form.errors.homeroom_teacher_id}>
                        <select id="homeroom" className="input" value={form.data.homeroom_teacher_id} onChange={(event) => form.setData('homeroom_teacher_id', event.target.value)}>
                            <option value="">— Belum ditentukan —</option>
                            {teachers.map((teacher) => {
                                const takenBy = teacher.classroomId && teacher.classroomId !== editingId ? classrooms.find((classroom) => classroom.id === teacher.classroomId)?.name : null;

                                return (
                                    <option key={teacher.id} value={teacher.id} disabled={Boolean(takenBy)}>
                                        {teacher.name}
                                        {takenBy ? ` (wali ${takenBy})` : ''}
                                    </option>
                                );
                            })}
                        </select>
                    </Field>
                    <label className="wa-toggle" htmlFor="has_room" style={{ marginBottom: 16 }}>
                        <input id="has_room" type="checkbox" checked={form.data.has_room} onChange={(event) => form.setData('has_room', event.target.checked)} />
                        <span>
                            <b>Kelas ini punya ruang sendiri</b>
                            <span className="cell-sub">Matikan bila kelas tidak kebagian ruangan: siswanya belajar di kelas lain, tetapi absensi, rekap, dan rapor tetap di kelas ini.</span>
                        </span>
                    </label>
                    {teachers.length === 0 && (
                        <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                            Belum ada akun wali kelas. Buat dulu di menu <Link href="/pengguna">Pengguna</Link>.
                        </p>
                    )}
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

            <Modal open={batchOpen} title="Buat kelas massal" onClose={() => setBatchOpen(false)}>
                <form onSubmit={submitBatch} noValidate>
                    <Field label="Tingkat" error={batchForm.errors.grades}>
                        <div style={{ display: 'flex', gap: 16 }}>
                            {grades.map((grade) => (
                                <label key={grade.value} className="radio checkbox">
                                    <input type="checkbox" checked={batchForm.data.grades.includes(grade.value)} onChange={() => toggleBatchGrade(grade.value)} />
                                    <span className="dot" />
                                    {grade.label}
                                </label>
                            ))}
                        </div>
                    </Field>
                    <Field label="Jumlah rombel per tingkat" htmlFor="count" error={batchForm.errors.count} hint={`Membuat rombel A sampai ${String.fromCharCode(64 + Math.min(Math.max(Number(batchForm.data.count) || 1, 1), 26))}. Kelas yang sudah ada dilewati.`}>
                        <input id="count" type="number" min={1} max={26} className="input" value={batchForm.data.count} onChange={(event) => batchForm.setData('count', event.target.value)} />
                    </Field>
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={() => setBatchOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={batchForm.processing}>
                            Buat kelas
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={lodging !== null}
                title={`Titipkan siswa ${lodging?.name} ke kelas lain?`}
                confirmLabel="Bagi rata"
                onConfirm={() => router.post(`/kelas/${lodging.id}/titipkan`, {}, { preserveScroll: true, onFinish: () => setLodging(null) })}
                onClose={() => setLodging(null)}
            >
                Siswa aktif {lodging?.name} dibagi rata ke kelas setingkat yang punya ruang, mulai dari kelas yang paling sedikit siswanya. Mereka belajar di kelas itu, tetapi absensi, rekap, dan rapornya tetap milik {lodging?.name} dan dipantau wali kelasnya. Pembagian bisa diubah per siswa di menu Siswa (kolom Belajar di).
            </ConfirmDialog>

            <ConfirmDialog
                open={returning !== null}
                title={`Kembalikan siswa ${returning?.name} ke kelasnya?`}
                confirmLabel="Kembalikan"
                onConfirm={() => router.delete(`/kelas/${returning.id}/titipkan`, { preserveScroll: true, onFinish: () => setReturning(null) })}
                onClose={() => setReturning(null)}
            >
                {returning?.name} ditandai punya ruang sendiri dan {returning?.lodged} siswa titipannya kembali belajar di {returning?.name}. Titipan yang ikut terbawa saat kenaikan kelas juga berakhir di sini.
            </ConfirmDialog>

            <ConfirmDialog open={deleting !== null} title={`Hapus kelas ${deleting?.name}?`} confirmLabel="Hapus" onConfirm={destroy} onClose={() => setDeleting(null)}>
                Kelas yang masih berisi siswa tidak bisa dihapus.
            </ConfirmDialog>
        </>
    );
}

ClassroomsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
