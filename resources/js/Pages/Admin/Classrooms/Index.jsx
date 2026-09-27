import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Layers, Plus, RefreshCw, Shuffle, WandSparkles } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import AppLayout from '@/Layouts/AppLayout';

const RANDOM_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

function randomCode() {
    const values = crypto.getRandomValues(new Uint32Array(6));

    return Array.from(values, (value) => RANDOM_ALPHABET[value % RANDOM_ALPHABET.length]).join('');
}

export default function ClassroomsIndex({ academicYear, classrooms, teachers, grades, codePrefix }) {
    const [editing, setEditing] = useState(null);
    const [batchOpen, setBatchOpen] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const [regenerating, setRegenerating] = useState(null);
    const [showCodes, setShowCodes] = useState(true);
    const [standardizing, setStandardizing] = useState(false);

    const form = useForm({ grade: 7, section: '', homeroom_teacher_id: '', access_code: '' });
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
        form.setData({ grade: 7, section: '', homeroom_teacher_id: '', access_code: '' });
        setEditing('new');
    }

    function openEdit(classroom) {
        form.clearErrors();
        form.setData({ grade: classroom.grade, section: classroom.section, homeroom_teacher_id: classroom.homeroomTeacher?.id ?? '', access_code: classroom.accessCode });
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

    function regenerate() {
        router.put(`/kelas/${regenerating.id}/kode`, {}, { preserveScroll: true, onFinish: () => setRegenerating(null) });
    }

    function destroy() {
        router.delete(`/kelas/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    const editingId = editing === 'new' ? null : editing?.id;
    const standardCode = form.data.section ? `${codePrefix}${form.data.grade}${form.data.section}`.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12) : '';
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
                    <button type="button" className="btn btn-secondary" onClick={() => setShowCodes((value) => !value)}>
                        {showCodes ? <EyeOff size={16} aria-hidden="true" /> : <Eye size={16} aria-hidden="true" />}
                        {showCodes ? 'Sembunyikan kode' : 'Tampilkan kode'}
                    </button>
                    <button type="button" className="btn btn-secondary" onClick={() => setStandardizing(true)}>
                        <WandSparkles size={16} aria-hidden="true" /> Kode standar semua kelas
                    </button>
                    <button type="button" className="btn btn-secondary" onClick={() => { batchForm.clearErrors(); setBatchOpen(true); }}>
                        <Layers size={16} aria-hidden="true" /> Buat kelas massal
                    </button>
                    <button type="button" className="btn btn-primary" onClick={openCreate}>
                        <Plus size={16} aria-hidden="true" /> Tambah kelas
                    </button>
                </div>
            </div>

            <p className="text-muted" style={{ maxWidth: 680 }}>
                {classrooms.length} kelas · {totalStudents.toLocaleString('id-ID')} siswa. Kode kelas dipakai guru jam pertama untuk membuka scanner di HP. Kode standar mengikuti pola {codePrefix} + tingkat + rombel (VII-A → {codePrefix}7A). Tekan ⟳ di samping kode untuk membuat kode acak kalau kode sebuah kelas perlu dirahasiakan.
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
                                            <th style={{ width: 180 }}>Kode kelas</th>
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
                                                </td>
                                                <td>
                                                    <span className="mono">{showCodes ? classroom.accessCode : '••••••'}</span>
                                                    <button type="button" className="btn btn-ghost" title="Buat kode acak" aria-label={`Buat kode acak untuk kelas ${classroom.name}`} onClick={() => setRegenerating(classroom)}>
                                                        <RefreshCw size={14} aria-hidden="true" />
                                                    </button>
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
                    <Field
                        label="Kode kelas"
                        htmlFor="access_code"
                        error={form.errors.access_code}
                        hint={editing === 'new' ? `Kosongkan untuk kode standar (${standardCode || codePrefix + '7A'}), atau tulis sendiri 4–12 huruf/angka.` : '4–12 huruf/angka. Kalau diganti, kode lama langsung tidak berlaku.'}
                    >
                        <div className="code-field">
                            <input
                                id="access_code"
                                className="input mono"
                                maxLength={12}
                                autoComplete="off"
                                placeholder={editing === 'new' ? standardCode || 'Otomatis' : ''}
                                value={form.data.access_code}
                                onChange={(event) => form.setData('access_code', event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))}
                            />
                            <button type="button" className="btn btn-secondary" disabled={!standardCode} onClick={() => form.setData('access_code', standardCode)} title="Pakai kode standar">
                                <WandSparkles size={16} aria-hidden="true" /> Standar
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={() => form.setData('access_code', randomCode())} title="Buat kode acak">
                                <Shuffle size={16} aria-hidden="true" /> Acak
                            </button>
                        </div>
                    </Field>
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

            <ConfirmDialog open={regenerating !== null} title={`Buat kode acak untuk ${regenerating?.name}?`} confirmLabel="Buat kode acak" onConfirm={regenerate} onClose={() => setRegenerating(null)}>
                Kode lama langsung tidak berlaku. Berikan kode baru ke guru yang mengajar jam pertama di kelas ini.
            </ConfirmDialog>

            <ConfirmDialog open={standardizing} title="Pakai kode standar untuk semua kelas?" confirmLabel="Pakai kode standar" onConfirm={() => router.post('/kelas/kode-standar', {}, { preserveScroll: true, onFinish: () => setStandardizing(false) })} onClose={() => setStandardizing(false)}>
                Semua kelas di tahun ajaran {academicYear.name} memakai kode {codePrefix} + tingkat + rombel, misalnya VII-A → {codePrefix}7A dan IX-K → {codePrefix}9K. Kode acak yang pernah dibuat tidak berlaku lagi.
            </ConfirmDialog>

            <ConfirmDialog open={deleting !== null} title={`Hapus kelas ${deleting?.name}?`} confirmLabel="Hapus" onConfirm={destroy} onClose={() => setDeleting(null)}>
                Kelas yang masih berisi siswa tidak bisa dihapus.
            </ConfirmDialog>
        </>
    );
}

ClassroomsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
