import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

const RISK_FIELDS = [
    ['window_days', 'Periode yang dihitung (hari)', 'Aturan di bawah melihat absensi selama periode ini. Siswa yang membaik otomatis keluar dari daftar.'],
    ['absent_watch', 'Alpa — perlu perhatian (kali)'],
    ['absent_high', 'Alpa — risiko tinggi (kali)'],
    ['rate_watch', 'Kehadiran di bawah — perlu perhatian (%)'],
    ['rate_high', 'Kehadiran di bawah — risiko tinggi (%)'],
    ['semester_rate_high', 'Kehadiran semester di bawah — risiko tinggi (%)', 'Satu-satunya aturan yang melihat seluruh semester, karena berpengaruh ke rapor.'],
    ['late_watch', 'Terlambat — perlu perhatian (kali)'],
    ['excused_watch', 'Sakit + izin — perlu perhatian (kali)'],
];

export default function Settings({ settings, general, timezones, holidays, riskSettings }) {
    const riskForm = useForm(riskSettings);
    const form = useForm(settings);
    const generalForm = useForm(general);
    const holidayForm = useForm({ from: '', to: '', description: '' });
    const [deleting, setDeleting] = useState(null);

    function updateDay(index, changes) {
        form.setData('days', form.data.days.map((day, position) => (position === index ? { ...day, ...changes } : day)));
    }

    function submitSettings(event) {
        event.preventDefault();
        form.put('/pengaturan', { preserveScroll: true });
    }

    function submitGeneral(event) {
        event.preventDefault();
        generalForm.put('/pengaturan/umum', { preserveScroll: true });
    }

    function submitHoliday(event) {
        event.preventDefault();
        holidayForm.post('/libur', { preserveScroll: true, onSuccess: () => holidayForm.reset() });
    }

    function destroyHoliday() {
        router.delete(`/libur/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    }

    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const upcoming = holidays.filter((holiday) => holiday.date >= today);
    const past = holidays.filter((holiday) => !upcoming.includes(holiday));

    return (
        <>
            <Head title="Pengaturan" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Admin</h6>
                    <h1>Pengaturan absensi</h1>
                </div>
                <div className="page-actions">
                    <Link href="/notifikasi-wa" className="btn btn-secondary">
                        Notifikasi WhatsApp
                    </Link>
                </div>
            </div>

            <form onSubmit={submitGeneral} noValidate className="excuse-form" style={{ marginBottom: 40, maxWidth: 720 }}>
                <h4 style={{ margin: 0 }}>Umum</h4>
                <div className="form-grid">
                    <Field label="Nama aplikasi" htmlFor="app_name" error={generalForm.errors.app_name} hint="Tampil di halaman login dan halaman kode, misalnya Absensi atau SiHadir.">
                        <input id="app_name" className="input" value={generalForm.data.app_name} onChange={(event) => generalForm.setData('app_name', event.target.value)} />
                    </Field>
                    <Field label="Zona waktu" htmlFor="timezone" error={generalForm.errors.timezone} hint="Jam buka gerbang, jam masuk, dan status Terlambat mengikuti zona waktu ini.">
                        <select id="timezone" className="input" value={generalForm.data.timezone} onChange={(event) => generalForm.setData('timezone', event.target.value)}>
                            {timezones.map((timezone) => (
                                <option key={timezone.value} value={timezone.value}>
                                    {timezone.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
                <div className="form-grid">
                    <Field label="Nama sekolah" htmlFor="school_name" error={generalForm.errors.school_name} hint="Tampil di kartu QR, halaman login, rekap Excel, dan pesan WhatsApp.">
                        <input id="school_name" className="input" value={generalForm.data.school_name} onChange={(event) => generalForm.setData('school_name', event.target.value)} />
                    </Field>
                    <Field label="Selesaikan absen otomatis pukul" htmlFor="finalize_at" error={generalForm.errors.finalize_at} hint='Kalau guru piket lupa menekan "Selesaikan absen", siswa yang belum absen dicatat Alpa pada jam ini.'>
                        <input id="finalize_at" type="time" className="input" value={generalForm.data.finalize_at} onChange={(event) => generalForm.setData('finalize_at', event.target.value)} />
                    </Field>
                </div>
                <label className="wa-toggle" htmlFor="demo_mode">
                    <input id="demo_mode" type="checkbox" checked={generalForm.data.demo_mode} onChange={(event) => generalForm.setData('demo_mode', event.target.checked)} />
                    <span>
                        <b>Mode demo</b>
                        <span className="cell-sub">
                            Untuk latihan dan presentasi saja. Selama aktif, jadwal tidak dipakai: tab gerbang selalu terbuka (juga di hari libur), guru piket selalu mencatat Hadir (tidak pernah Terlambat), dan absen bisa diselesaikan kapan saja. Matikan saat
                            dipakai sungguhan di sekolah.
                        </span>
                    </span>
                </label>
                <div>
                    <button type="submit" className="btn btn-primary" disabled={generalForm.processing}>
                        Simpan
                    </button>
                </div>
            </form>

            <div className="settings-layout">
                <form onSubmit={submitSettings} noValidate className="excuse-form">
                    <h4 style={{ margin: 0 }}>Jadwal absensi</h4>
                    <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                        Tab di gerbang hanya menerima scan di antara jam buka dan jam masuk. Siswa yang datang setelah jam masuk atau lupa membawa kartu diabsenkan oleh guru piket.
                    </p>
                    {form.errors.days && <p className="field-error">{form.errors.days}</p>}
                    <div className="table-wrap">
                        <table className="table schedule-table">
                            <thead>
                                <tr>
                                    <th>Hari sekolah</th>
                                    <th>Jam buka</th>
                                    <th>Jam masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.days.map((day, index) => (
                                    <tr key={day.day} className={day.active ? undefined : 'is-muted'}>
                                        <td>
                                            <label className="radio checkbox">
                                                <input type="checkbox" checked={day.active} onChange={(event) => updateDay(index, { active: event.target.checked })} />
                                                <span className="dot" />
                                                {day.label}
                                            </label>
                                        </td>
                                        <td>
                                            <input
                                                type="time"
                                                className="input"
                                                aria-label={`Jam buka ${day.label}`}
                                                value={day.opens_at}
                                                disabled={!day.active}
                                                onChange={(event) => updateDay(index, { opens_at: event.target.value })}
                                            />
                                            {form.errors[`days.${index}.opens_at`] && <p className="field-error">{form.errors[`days.${index}.opens_at`]}</p>}
                                        </td>
                                        <td>
                                            <input
                                                type="time"
                                                className="input"
                                                aria-label={`Jam masuk ${day.label}`}
                                                value={day.closes_at}
                                                disabled={!day.active}
                                                onChange={(event) => updateDay(index, { closes_at: event.target.value })}
                                            />
                                            {form.errors[`days.${index}.closes_at`] && <p className="field-error">{form.errors[`days.${index}.closes_at`]}</p>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan jadwal
                        </button>
                    </div>
                </form>

                <section style={{ minWidth: 0 }}>
                    <h4>Hari libur</h4>
                    <p className="text-muted" style={{ fontSize: 13 }}>
                        Pada hari libur gerbang tidak dibuka, dan izin/sakit tidak dihitung.
                    </p>
                    <form onSubmit={submitHoliday} noValidate className="holiday-form">
                        <Field label="Tanggal" htmlFor="holiday-from" error={holidayForm.errors.from}>
                            <input id="holiday-from" type="date" className="input" value={holidayForm.data.from} onChange={(event) => holidayForm.setData('from', event.target.value)} />
                        </Field>
                        <Field label="Sampai (opsional)" htmlFor="holiday-to" error={holidayForm.errors.to}>
                            <input id="holiday-to" type="date" className="input" min={holidayForm.data.from} value={holidayForm.data.to} onChange={(event) => holidayForm.setData('to', event.target.value)} />
                        </Field>
                        <Field label="Keterangan" htmlFor="holiday-description" error={holidayForm.errors.description}>
                            <input id="holiday-description" className="input" placeholder="Contoh: Maulid Nabi" value={holidayForm.data.description} onChange={(event) => holidayForm.setData('description', event.target.value)} />
                        </Field>
                        <button type="submit" className="btn btn-secondary" disabled={holidayForm.processing}>
                            Tambah libur
                        </button>
                    </form>

                    <div className="scan-roster" style={{ borderTop: '2px solid var(--color-text)', marginTop: 16 }}>
                        {upcoming.length === 0 && <p className="text-muted">Belum ada hari libur mendatang.</p>}
                        {[...upcoming, ...[...past].reverse()].map((holiday) => (
                            <div key={holiday.id} className="scan-roster-row" style={upcoming.includes(holiday) ? undefined : { opacity: 0.55 }}>
                                <span style={{ width: 110 }}>{formatDate(holiday.date)}</span>
                                <span style={{ flex: 1 }}>{holiday.description}</span>
                                <button type="button" className="btn btn-ghost" onClick={() => setDeleting(holiday)}>
                                    Hapus
                                </button>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            <form onSubmit={(event) => { event.preventDefault(); riskForm.put('/pengaturan/risiko', { preserveScroll: true }); }} noValidate className="excuse-form" style={{ marginTop: 40 }}>
                <h4 style={{ margin: 0 }}>Aturan siswa berisiko</h4>
                <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                    Siswa masuk daftar Siswa berisiko bila memenuhi salah satu aturan. Daftar diperbarui setiap hari; siswa yang sudah membaik keluar dari daftar dan datanya dihapus.
                </p>
                <div className="risk-settings-grid">
                    {RISK_FIELDS.map(([key, label, hint]) => (
                        <Field key={key} label={label} htmlFor={`risk_${key}`} error={riskForm.errors[key]} hint={hint}>
                            <input id={`risk_${key}`} type="number" min={1} className="input" value={riskForm.data[key]} onChange={(event) => riskForm.setData(key, event.target.value)} />
                        </Field>
                    ))}
                </div>
                <div>
                    <button type="submit" className="btn btn-primary" disabled={riskForm.processing || !riskForm.isDirty}>
                        Simpan aturan
                    </button>
                </div>
            </form>

            <ConfirmDialog open={deleting !== null} title={`Hapus libur ${deleting ? formatDate(deleting.date) : ''}?`} confirmLabel="Hapus" onConfirm={destroyHoliday} onClose={() => setDeleting(null)}>
                Tanggal ini kembali menjadi hari sekolah biasa.
            </ConfirmDialog>
        </>
    );
}

Settings.layout = (page) => <AppLayout>{page}</AppLayout>;
