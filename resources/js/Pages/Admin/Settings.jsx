import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

const WEEKDAYS = [
    [1, 'Senin'],
    [2, 'Selasa'],
    [3, 'Rabu'],
    [4, 'Kamis'],
    [5, 'Jumat'],
    [6, 'Sabtu'],
    [7, 'Minggu'],
];

export default function Settings({ settings, isEnforced, holidays }) {
    const form = useForm(settings);
    const holidayForm = useForm({ from: '', to: '', description: '' });
    const [deleting, setDeleting] = useState(null);

    function toggleDay(day) {
        const days = form.data.school_days;
        form.setData('school_days', days.includes(day) ? days.filter((value) => value !== day) : [...days, day].sort());
    }

    function submitSettings(event) {
        event.preventDefault();
        form.put('/pengaturan', { preserveScroll: true });
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
            </div>

            {!isEnforced && (
                <p className="alert">
                    Mode demo aktif (<code>ATTENDANCE_ENFORCE_WINDOW=false</code>): kode kelas bisa dipakai di luar jam dan hari sekolah. Di server sekolah, ubah menjadi <code>true</code> di file .env.
                </p>
            )}

            <div className="settings-layout">
                <form onSubmit={submitSettings} noValidate className="excuse-form">
                    <h4 style={{ margin: 0 }}>Jam scan kelas</h4>
                    <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                        Kode kelas hanya bisa dipakai di antara jam ini. Pada jam tutup, kelas yang belum menekan "Selesai" ditutup otomatis dan siswa yang belum absen dicatat Alpa.
                    </p>
                    <div className="form-grid">
                        <Field label="Jam buka" htmlFor="opens_at" error={form.errors.opens_at}>
                            <input id="opens_at" type="time" className="input" value={form.data.opens_at} onChange={(event) => form.setData('opens_at', event.target.value)} />
                        </Field>
                        <Field label="Jam tutup" htmlFor="closes_at" error={form.errors.closes_at}>
                            <input id="closes_at" type="time" className="input" value={form.data.closes_at} onChange={(event) => form.setData('closes_at', event.target.value)} />
                        </Field>
                    </div>
                    <Field label="Hari sekolah" error={form.errors.school_days}>
                        <div className="day-picker">
                            {WEEKDAYS.map(([day, label]) => (
                                <label key={day} className="radio checkbox">
                                    <input type="checkbox" checked={form.data.school_days.includes(day)} onChange={() => toggleDay(day)} />
                                    <span className="dot" />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </Field>
                    <div>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan pengaturan
                        </button>
                    </div>
                </form>

                <section style={{ minWidth: 0 }}>
                    <h4>Hari libur</h4>
                    <p className="text-muted" style={{ fontSize: 13 }}>
                        Pada hari libur absensi kelas tidak dibuka, dan izin/sakit tidak dihitung.
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

            <ConfirmDialog open={deleting !== null} title={`Hapus libur ${deleting ? formatDate(deleting.date) : ''}?`} confirmLabel="Hapus" onConfirm={destroyHoliday} onClose={() => setDeleting(null)}>
                Tanggal ini kembali menjadi hari sekolah biasa.
            </ConfirmDialog>
        </>
    );
}

Settings.layout = (page) => <AppLayout>{page}</AppLayout>;
