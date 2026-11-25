import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';

/**
 * Jadwal mingguan guru per hari (jadwal piket, jadwal sapa pagi): tambah dan
 * hapus guru per hari, lalu simpan sekaligus.
 */
export default function WeeklyScheduleForm({ weekdays, teachers, url, dutyLabel }) {
    const form = useForm({
        days: Object.fromEntries(weekdays.map((day) => [day.value, day.userIds])),
    });
    const teacherById = Object.fromEntries(teachers.map((teacher) => [teacher.id, teacher]));
    const isDirty = weekdays.some((day) => day.userIds.join(',') !== form.data.days[day.value].join(','));

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

    function submit(event) {
        event.preventDefault();
        form.put(url, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} noValidate>
            {form.errors.days && <p className="field-error">{form.errors.days}</p>}
            <div className="table-wrap">
                <table className="table schedule-table">
                    <thead>
                        <tr>
                            <th style={{ width: 110 }}>Hari</th>
                            <th>{dutyLabel}</th>
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
                                            <span className="text-muted">Belum ada {dutyLabel.toLowerCase()}</span>
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
                                        <select className="input" aria-label={`Tambah ${dutyLabel.toLowerCase()} hari ${day.label}`} value="" disabled={available.length === 0} onChange={(event) => addTeacher(day.value, Number(event.target.value))}>
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
    );
}
