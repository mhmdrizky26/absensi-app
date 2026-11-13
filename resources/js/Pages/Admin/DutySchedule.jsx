import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';

/**
 * Senin = 1 … Jumat = 5, sama seperti di server (ISO).
 */
function weekdayOf(date) {
    if (!date) {
        return null;
    }

    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).getDay() || 7;
}

const pad = (number) => String(number).padStart(2, '0');
const now = new Date();
const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

export default function DutySchedule({ weekdays, teachers, substitutions }) {
    const form = useForm({
        days: Object.fromEntries(weekdays.map((day) => [day.value, day.userIds])),
    });
    const substitutionForm = useForm({
        date: today,
        absent_user_id: '',
        substitute_user_id: '',
    });
    const [deleting, setDeleting] = useState(null);
    const teacherById = Object.fromEntries(teachers.map((teacher) => [teacher.id, teacher]));

    function addTeacher(weekday, userId) {
        if (userId && !form.data.days[weekday].includes(userId)) {
            form.setData('days', {
                ...form.data.days,
                [weekday]: [...form.data.days[weekday], userId],
            });
        }
    }

    function removeTeacher(weekday, userId) {
        form.setData('days', {
            ...form.data.days,
            [weekday]: form.data.days[weekday].filter((id) => id !== userId),
        });
    }

    function submitSchedule(event) {
        event.preventDefault();
        form.put('/jadwal-piket', { preserveScroll: true });
    }

    function submitSubstitution(event) {
        event.preventDefault();
        substitutionForm.post('/jadwal-piket/pengganti', {
            preserveScroll: true,
            onSuccess: () => substitutionForm.reset('absent_user_id', 'substitute_user_id'),
        });
    }

    // Guru yang bisa diganti: yang terjadwal di hari tanggal itu (jadwal yang sudah disimpan).
    const substitutionWeekday = weekdayOf(substitutionForm.data.date);
    const savedDay = weekdays.find((day) => day.value === substitutionWeekday);
    const scheduledThatDay = (savedDay?.userIds ?? []).map((id) => teacherById[id]).filter(Boolean);
    const isDirty = weekdays.some((day) => day.userIds.join(',') !== form.data.days[day.value].join(','));

    return (
        <>
            <Head title="Jadwal piket" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Master data</h6>
                    <h1>Jadwal piket</h1>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Jadwal yang sama setiap minggu. Menu piket (Pantauan, Absen piket, Selesaikan absen, persetujuan dispensasi) hanya terbuka untuk guru piket di hari jadwalnya. Admin selalu bisa menjalankan tugas piket. Akun guru dibuat di menu{' '}
                <Link href="/pengguna">Pengguna</Link>.
            </p>

            {teachers.length === 0 ? (
                <div className="empty">
                    <h4>Belum ada akun guru</h4>
                    <p className="text-muted">Buat akun guru dulu di menu Pengguna, lalu kembali ke sini.</p>
                </div>
            ) : (
                <form onSubmit={submitSchedule} noValidate>
                    {form.errors.days && <p className="field-error">{form.errors.days}</p>}
                    <div className="table-wrap">
                        <table className="table schedule-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 110 }}>Hari</th>
                                    <th>Guru piket</th>
                                    <th style={{ width: 260 }}>Tambah guru</th>
                                </tr>
                            </thead>
                            <tbody>
                                {weekdays.map((day) => {
                                    const assigned = form.data.days[day.value];
                                    const available = teachers.filter((teacher) => !assigned.includes(teacher.id));

                                    return (
                                        <tr key={day.value}>
                                            <td style={{ fontWeight: 700 }}>
                                                {day.label}
                                                <span className="cell-sub">{assigned.length} guru</span>
                                            </td>
                                            <td>
                                                {assigned.length === 0 ? (
                                                    <span className="text-muted">Belum ada guru piket</span>
                                                ) : (
                                                    <div className="chip-list" style={{ marginTop: 0 }}>
                                                        {assigned.map((id) => (
                                                            <span
                                                                key={id}
                                                                className="tag tag-neutral"
                                                                style={{
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    gap: 4,
                                                                }}
                                                            >
                                                                {teacherById[id]?.name ?? 'Akun nonaktif'}
                                                                {teacherById[id]?.homeroom && <span className="text-muted">· wali {teacherById[id].homeroom}</span>}
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost"
                                                                    style={{
                                                                        padding: 0,
                                                                        minHeight: 0,
                                                                    }}
                                                                    aria-label={`Hapus ${teacherById[id]?.name ?? 'guru'} dari ${day.label}`}
                                                                    onClick={() => removeTeacher(day.value, id)}
                                                                >
                                                                    <X size={14} aria-hidden="true" />
                                                                </button>
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                                {Object.keys(form.errors)
                                                    .filter((key) => key.startsWith(`days.${day.value}.`))
                                                    .map((key) => (
                                                        <p key={key} className="field-error">
                                                            {form.errors[key]}
                                                        </p>
                                                    ))}
                                            </td>
                                            <td>
                                                <select className="input" aria-label={`Tambah guru piket hari ${day.label}`} value="" disabled={available.length === 0} onChange={(event) => addTeacher(day.value, Number(event.target.value))}>
                                                    <option value="">Pilih guru…</option>
                                                    {available.map((teacher) => (
                                                        <option key={teacher.id} value={teacher.id}>
                                                            {teacher.name}
                                                            {teacher.homeroom ? ` (wali ${teacher.homeroom})` : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            marginTop: 12,
                        }}
                    >
                        <button type="submit" className="btn btn-primary" disabled={form.processing || !isDirty}>
                            Simpan jadwal
                        </button>
                        {isDirty && (
                            <span className="text-muted" style={{ fontSize: 13 }}>
                                Ada perubahan yang belum disimpan.
                            </span>
                        )}
                    </div>
                </form>
            )}

            <h4 className="section-title">Pengganti piket</h4>
            <p className="text-muted" style={{ fontSize: 13, maxWidth: 760 }}>
                Kalau guru piket berhalangan, catat penggantinya untuk tanggal itu saja. Pada tanggal itu hanya pengganti yang mendapat menu piket; minggu berikutnya jadwal tetap berlaku lagi.
            </p>
            <form onSubmit={submitSubstitution} noValidate className="holiday-form">
                <Field label="Tanggal" htmlFor="substitution-date" error={substitutionForm.errors.date}>
                    <input
                        id="substitution-date"
                        type="date"
                        className="input"
                        min={today}
                        value={substitutionForm.data.date}
                        onChange={(event) =>
                            substitutionForm.setData({
                                ...substitutionForm.data,
                                date: event.target.value,
                                absent_user_id: '',
                            })
                        }
                    />
                </Field>
                <Field label="Guru yang diganti" htmlFor="substitution-absent" error={substitutionForm.errors.absent_user_id}>
                    <select id="substitution-absent" className="input" value={substitutionForm.data.absent_user_id} onChange={(event) => substitutionForm.setData('absent_user_id', event.target.value)}>
                        <option value="">{scheduledThatDay.length === 0 ? 'Tidak ada jadwal di hari itu' : 'Pilih guru…'}</option>
                        {scheduledThatDay.map((teacher) => (
                            <option key={teacher.id} value={teacher.id}>
                                {teacher.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Guru pengganti" htmlFor="substitution-substitute" error={substitutionForm.errors.substitute_user_id}>
                    <select id="substitution-substitute" className="input" value={substitutionForm.data.substitute_user_id} onChange={(event) => substitutionForm.setData('substitute_user_id', event.target.value)}>
                        <option value="">Pilih guru…</option>
                        {teachers
                            .filter((teacher) => String(teacher.id) !== String(substitutionForm.data.absent_user_id) && !scheduledThatDay.includes(teacher))
                            .map((teacher) => (
                                <option key={teacher.id} value={teacher.id}>
                                    {teacher.name}
                                </option>
                            ))}
                    </select>
                </Field>
                <button type="submit" className="btn btn-secondary" disabled={substitutionForm.processing || !substitutionForm.data.absent_user_id || !substitutionForm.data.substitute_user_id}>
                    <Plus size={16} aria-hidden="true" /> Catat pengganti
                </button>
            </form>

            <div style={{ marginTop: 16 }}>
                {substitutions.length === 0 ? (
                    <p className="text-muted">Belum ada pengganti untuk hari ini atau tanggal mendatang.</p>
                ) : (
                    substitutions.map((substitution) => (
                        <div key={substitution.id} className="scan-roster-row">
                            <span style={{ width: 110 }}>{formatDate(substitution.date)}</span>
                            <span style={{ flex: 1 }}>
                                <b>{substitution.substitute}</b> menggantikan {substitution.absent}
                            </span>
                            <button type="button" className="btn btn-ghost" onClick={() => setDeleting(substitution)}>
                                Hapus
                            </button>
                        </div>
                    ))
                )}
            </div>

            <ConfirmDialog
                open={deleting !== null}
                title="Hapus pengganti?"
                confirmLabel="Hapus"
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/jadwal-piket/pengganti/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
            >
                {deleting && `${deleting.absent} kembali piket pada ${formatDate(deleting.date)}, dan ${deleting.substitute} tidak.`}
            </ConfirmDialog>
        </>
    );
}

DutySchedule.layout = (page) => <AppLayout>{page}</AppLayout>;
