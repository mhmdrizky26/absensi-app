import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Upload } from 'lucide-react';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';

export default function UsersImport({ academicYear, classroomNames }) {
    const { flash } = usePage().props;
    const form = useForm({ file: null });

    function submit(event) {
        event.preventDefault();
        form.post('/pengguna/import', { forceFormData: true });
    }

    return (
        <>
            <Head title="Import guru" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        <Link href="/pengguna" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                            <ArrowLeft size={14} aria-hidden="true" /> Pengguna
                        </Link>
                    </h6>
                    <h1>Import guru dari Excel</h1>
                </div>
            </div>

            <div className="import-layout">
                <ol className="steps">
                    <li>
                        <div>
                            <h5>Unduh template</h5>
                            <p className="text-muted" style={{ fontSize: 14 }}>
                                Kolom: <b>Nama</b> (wajib), <b>Peran</b> (guru atau guru bk; kosong dianggap guru), <b>Wali Kelas</b> (kelas yang dipegang, boleh kosong), <b>Username</b> dan <b>Password</b> yang boleh dikosongkan, serta{' '}
                                <b>Hari Piket</b> (misalnya Senin, Rabu; boleh kosong). Semua guru, termasuk guru mapel, perlu akun untuk melihat absen kelas hari ini.
                            </p>
                            <a href="/pengguna/import/template" className="btn btn-secondary">
                                <Download size={16} aria-hidden="true" /> Template Excel
                            </a>
                        </div>
                    </li>
                    <li>
                        <div>
                            <h5>Isi data guru</h5>
                            <p className="text-muted" style={{ fontSize: 14, marginBottom: 0 }}>
                                Username kosong dibuat dari nama tanpa gelar (Budi Santoso, S.Pd. → budi.santoso). Password kosong diisi <b>12345</b>; guru bisa menggantinya sendiri lewat menu profil. Guru yang sudah punya akun (username atau namanya sama)
                                diperbarui datanya; passwordnya hanya diganti kalau kolom Password diisi.
                            </p>
                            {academicYear && classroomNames.length > 0 && (
                                <>
                                    <p className="text-muted" style={{ fontSize: 13, margin: '8px 0 0' }}>
                                        Kelas di tahun ajaran {academicYear.name}:
                                    </p>
                                    <div className="chip-list">
                                        {classroomNames.map((name) => (
                                            <span key={name} className="tag tag-neutral">
                                                {name}
                                            </span>
                                        ))}
                                    </div>
                                </>
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
                                Kalau ada satu baris saja yang salah, tidak ada data yang disimpan. Setelah berhasil, unduh <b>daftar akun</b> berisi username dan password untuk dibagikan ke guru.
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
                                    {['Nama', 'Peran', 'Wali Kelas', 'Username', 'Password', 'Hari Piket'].map((heading) => (
                                        <th key={heading}>{heading}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[
                                    ['Budi Santoso, S.Pd.', 'guru', 'VII-A', '', '', 'Rabu'],
                                    ['Dewi Lestari, S.Pd.', 'guru', '', '', '', 'Senin, Kamis'],
                                    ['Sri Wahyuni, S.Psi.', 'guru bk', '', 'bu.sri', 'rahasia123', ''],
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
                        Dua baris pertama username dibuat otomatis dan password-nya 12345; baris ketiga memakai isian sendiri. Budi wali kelas VII-A sekaligus piket hari Rabu. Hari Piket yang dikosongkan tidak mengubah jadwal yang sudah ada.
                    </p>
                </aside>
            </div>
        </>
    );
}

UsersImport.layout = (page) => <AppLayout>{page}</AppLayout>;
