import { Head, useForm } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useState } from 'react';
import Field from '@/Components/Field';
import StudentPicker from '@/Components/StudentPicker';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

const todayIso = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

export default function Excuses({ excuses, homeroom, isHomeroomTeacher }) {
    const [student, setStudent] = useState(null);
    const [fileKey, setFileKey] = useState(0);
    const form = useForm({ student_id: '', status: 'S', from: todayIso(), to: todayIso(), note: '', attachment: null });

    function chooseStudent(chosen) {
        setStudent(chosen);
        form.setData('student_id', chosen?.id ?? '');
    }

    function submit(event) {
        event.preventDefault();
        form.post('/izin', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setStudent(null);
                setFileKey((key) => key + 1);
            },
        });
    }

    return (
        <>
            <Head title="Izin & sakit" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">{isHomeroomTeacher ? (homeroom ? `Wali kelas ${homeroom}` : 'Wali kelas') : 'Surat dari orang tua'}</h6>
                    <h1>Izin & sakit</h1>
                </div>
            </div>

            {isHomeroomTeacher && !homeroom && <p className="alert alert-error">Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif. Hubungi admin.</p>}

            <div className="excuse-layout">
                <form onSubmit={submit} noValidate className="excuse-form">
                    <h4 style={{ margin: 0 }}>Catat izin atau sakit</h4>

                    <Field label="Siswa" htmlFor="student" error={form.errors.student_id} hint={homeroom ? `Wali kelas hanya bisa memilih siswa kelas ${homeroom}.` : undefined}>
                        <StudentPicker id="student" value={student} onChange={chooseStudent} error={form.errors.student_id} date={form.data.from} placeholder={homeroom ? `Cari siswa kelas ${homeroom}` : undefined} />
                    </Field>

                    <Field label="Keterangan" error={form.errors.status}>
                        <div className="seg">
                            {[
                                ['S', 'Sakit'],
                                ['I', 'Izin'],
                            ].map(([value, label]) => (
                                <label key={value} className="seg-opt">
                                    <input type="radio" name="status" checked={form.data.status === value} onChange={() => form.setData('status', value)} />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </Field>

                    <div className="form-grid">
                        <Field label="Dari tanggal" htmlFor="from" error={form.errors.from}>
                            <input id="from" type="date" className="input" value={form.data.from} onChange={(event) => form.setData((data) => ({ ...data, from: event.target.value, to: data.to < event.target.value ? event.target.value : data.to }))} />
                        </Field>
                        <Field label="Sampai tanggal" htmlFor="to" error={form.errors.to}>
                            <input id="to" type="date" className="input" min={form.data.from} value={form.data.to} onChange={(event) => form.setData('to', event.target.value)} />
                        </Field>
                    </div>

                    <Field label="Alasan" htmlFor="note" error={form.errors.note} hint="Contoh: Demam, acara keluarga, dispensasi lomba">
                        <input id="note" className="input" maxLength={200} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />
                    </Field>

                    <Field label="Foto surat (opsional)" htmlFor="attachment" error={form.errors.attachment} hint="JPG, PNG, atau PDF, maks. 4 MB">
                        <input key={fileKey} id="attachment" type="file" className="input" accept="image/*,.pdf" capture="environment" onChange={(event) => form.setData('attachment', event.target.files[0] ?? null)} />
                    </Field>

                    <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                        Hari libur dan hari saat siswa ternyata hadir dilewati. Tanda Alpa pada tanggal itu diganti dengan keterangan ini.
                    </p>

                    <div>
                        <button type="submit" className="btn btn-primary" disabled={form.processing || !student}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </button>
                    </div>
                </form>

                <section style={{ minWidth: 0 }}>
                    <h4>7 hari terakhir & mendatang</h4>
                    {excuses.length === 0 ? (
                        <p className="text-muted">Belum ada izin atau sakit yang dicatat.</p>
                    ) : (
                        <div className="table-wrap">
                            <table className="table" style={{ minWidth: 560 }}>
                                <thead>
                                    <tr>
                                        <th style={{ width: 110 }}>Tanggal</th>
                                        <th>Siswa</th>
                                        <th style={{ width: 80 }}>Status</th>
                                        <th>Alasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {excuses.map((excuse) => (
                                        <tr key={excuse.id}>
                                            <td>{formatDate(excuse.date)}</td>
                                            <td>
                                                <span style={{ fontWeight: 600 }}>{excuse.name}</span>
                                                <span className="cell-sub">
                                                    {excuse.classroom} · NIS {excuse.nis}
                                                </span>
                                            </td>
                                            <td>
                                                <span className={`tag status-${excuse.status}`}>{excuse.statusLabel}</span>
                                            </td>
                                            <td>
                                                {excuse.note}
                                                <span className="cell-sub">
                                                    {excuse.recorder ? `oleh ${excuse.recorder}` : ''}
                                                    {excuse.hasAttachment && (
                                                        <>
                                                            {' · '}
                                                            <a href={`/surat/${excuse.id}`} target="_blank" rel="noreferrer">
                                                                <Paperclip size={12} aria-hidden="true" /> surat
                                                            </a>
                                                        </>
                                                    )}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Excuses.layout = (page) => <AppLayout>{page}</AppLayout>;
