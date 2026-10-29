import { useForm } from '@inertiajs/react';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';

/**
 * Wali kelas menambahkan nomor WA orang tua untuk siswa kelasnya, dari
 * halaman Rekap kelas. Siswa yang belum punya nomor ditampilkan paling atas.
 */
export default function ParentContactDialog({ students, relations, onClose }) {
    const sorted = [...students].sort((a, b) => (a.contacts.length > 0) - (b.contacts.length > 0) || a.name.localeCompare(b.name));
    const missing = students.filter((student) => student.contacts.length === 0).length;
    const form = useForm({ student_id: sorted[0]?.id ?? '', name: '', relation: 'ibu', phone: '', consented: true });
    const selected = students.find((student) => String(student.id) === String(form.data.student_id));

    function submit(event) {
        event.preventDefault();
        form.post(`/siswa-berisiko/${form.data.student_id}/kontak`, {
            preserveScroll: true,
            onSuccess: () => form.reset('name', 'phone'),
        });
    }

    return (
        <Modal open title="Nomor WA orang tua" onClose={onClose}>
            <p className="text-muted" style={{ marginTop: 0, fontSize: 13 }}>
                {missing > 0 ? `${missing} siswa belum punya nomor WA orang tua. ` : 'Semua siswa sudah punya nomor WA orang tua. '}
                Nomor dipakai untuk mengabari orang tua bila anaknya masuk daftar siswa berisiko.
            </p>
            <form onSubmit={submit} noValidate>
                <Field label="Siswa" htmlFor="contact_student" error={form.errors.student_id}>
                    <select id="contact_student" className="input" value={form.data.student_id} onChange={(event) => form.setData('student_id', event.target.value)}>
                        {sorted.map((student) => (
                            <option key={student.id} value={student.id}>
                                {student.name}
                                {student.contacts.length === 0 ? ' — belum ada nomor' : ` — ${student.contacts.length} nomor`}
                            </option>
                        ))}
                    </select>
                </Field>
                {selected?.contacts.length > 0 && <p className="text-muted" style={{ margin: '-8px 0 12px', fontSize: 13 }}>Nomor tersimpan: {selected.contacts.join(', ')}</p>}
                <Field label="Nama orang tua" htmlFor="contact_name" error={form.errors.name}>
                    <input id="contact_name" className="input" maxLength={100} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </Field>
                <div className="form-grid">
                    <Field label="Hubungan" htmlFor="contact_relation" error={form.errors.relation}>
                        <select id="contact_relation" className="input" value={form.data.relation} onChange={(event) => form.setData('relation', event.target.value)}>
                            {relations.map((relation) => (
                                <option key={relation.value} value={relation.value}>
                                    {relation.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="No. WA" htmlFor="contact_phone" error={form.errors.phone}>
                        <input id="contact_phone" className="input" inputMode="tel" placeholder="0812 3456 7890" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </Field>
                </div>
                <label className="wa-toggle" htmlFor="contact_consented" style={{ marginBottom: 8 }}>
                    <input id="contact_consented" type="checkbox" checked={form.data.consented} onChange={(event) => form.setData('consented', event.target.checked)} />
                    Orang tua setuju menerima pesan WhatsApp dari sekolah
                </label>
                <div className="dialog-actions">
                    <button type="button" className="btn btn-secondary" onClick={onClose}>
                        Tutup
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={form.processing || !form.data.phone || !form.data.student_id}>
                        {form.processing ? 'Menyimpan…' : 'Simpan nomor'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
