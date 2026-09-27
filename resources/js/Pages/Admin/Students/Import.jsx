import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Upload } from 'lucide-react';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';

export default function StudentsImport({ academicYear, classroomNames }) {
    const { flash } = usePage().props;
    const form = useForm({ file: null });

    function submit(event) {
        event.preventDefault();
        form.post('/siswa/import', { forceFormData: true });
    }

    return (
        <>
            <Head title="Import siswa" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        <Link href="/siswa" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                            <ArrowLeft size={14} aria-hidden="true" /> Siswa
                        </Link>
                    </h6>
                    <h1>Import siswa dari Excel</h1>
                </div>
            </div>

            {!academicYear ? (
                <div className="empty">
                    <h4>Belum ada tahun ajaran aktif</h4>
                    <p className="text-muted">Siswa diimport ke kelas pada tahun ajaran aktif. Aktifkan tahun ajaran dan buat kelasnya terlebih dahulu.</p>
                    <Link href="/tahun-ajaran" className="btn btn-primary">
                        Atur tahun ajaran
                    </Link>
                </div>
            ) : (
                <div className="import-layout">
                    <ol className="steps">
                        <li>
                            <div>
                                <h5>Unduh template</h5>
                                <p className="text-muted" style={{ fontSize: 14 }}>
                                    Kolom: <b>NIS</b>, <b>NISN</b> (boleh kosong), <b>Nama</b>, <b>JK</b> (L/P), dan <b>Kelas</b>. File ekspor Dapodik dengan kolom "Nama", "JK", dan "Rombel" juga bisa
                                    langsung dipakai.
                                </p>
                                <a href="/siswa/import/template" className="btn btn-secondary">
                                    <Download size={16} aria-hidden="true" /> Template Excel
                                </a>
                            </div>
                        </li>
                        <li>
                            <div>
                                <h5>Isi data siswa</h5>
                                <p className="text-muted" style={{ fontSize: 14, marginBottom: 0 }}>
                                    Nama kelas harus sama dengan kelas di tahun ajaran {academicYear.name}. Penulisan seperti "7A", "7-a", atau "VII A" otomatis dibaca sebagai VII-A. Siswa dengan NIS
                                    yang sudah terdaftar akan diperbarui datanya dan dipindah ke kelas di file.
                                </p>
                                {classroomNames.length > 0 ? (
                                    <div className="chip-list">
                                        {classroomNames.map((name) => (
                                            <span key={name} className="tag tag-neutral">
                                                {name}
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <p style={{ fontSize: 14, marginTop: 8 }}>
                                        Belum ada kelas. <Link href="/kelas">Buat kelas dulu</Link>.
                                    </p>
                                )}
                            </div>
                        </li>
                        <li>
                            <form onSubmit={submit} noValidate style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                <h5>Unggah file</h5>
                                <Field label="File .xlsx, .xls, atau .csv (maks. 5 MB)" htmlFor="file" error={form.errors.file}>
                                    <input id="file" type="file" className="input" accept=".xlsx,.xls,.csv" onChange={(event) => form.setData('file', event.target.files[0] ?? null)} />
                                </Field>
                                <div>
                                    <button type="submit" className="btn btn-primary" disabled={!form.data.file || form.processing}>
                                        <Upload size={16} aria-hidden="true" /> {form.processing ? 'Memproses…' : 'Import'}
                                    </button>
                                </div>
                                <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                                    Kalau ada satu baris saja yang salah, tidak ada data yang disimpan. Perbaiki barisnya lalu unggah ulang.
                                </p>
                            </form>
                        </li>
                    </ol>

                    <aside style={{ minWidth: 0 }}>
                        {flash.importErrors.length > 0 && (
                            <div role="alert" style={{ marginBottom: 32 }}>
                                <h4>Yang perlu diperbaiki</h4>
                                <ul className="error-list">
                                    {flash.importErrors.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <h4>Contoh isi file</h4>
                        <div className="table-wrap">
                            <table className="table">
                                <thead>
                                    <tr>
                                        {['NIS', 'NISN', 'Nama', 'JK', 'Kelas'].map((heading) => (
                                            <th key={heading}>{heading}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {[
                                        ['260001', '0012345678', 'Ayu Lestari', 'P', 'VII-A'],
                                        ['260002', '', 'Bayu Saputra', 'L', '7B'],
                                        ['250015', '0098765432', 'Citra Dewi', 'P', 'VIII A'],
                                    ].map((row) => (
                                        <tr key={row[0]}>
                                            {row.map((cell, index) => (
                                                <td key={index}>{cell || <span className="text-muted">(kosong)</span>}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="text-muted" style={{ fontSize: 13, marginTop: 8 }}>
                            Baris kedua dan ketiga menunjukkan bahwa NISN boleh kosong dan penulisan kelas boleh bervariasi.
                        </p>
                    </aside>
                </div>
            )}
        </>
    );
}

StudentsImport.layout = (page) => <AppLayout>{page}</AppLayout>;
