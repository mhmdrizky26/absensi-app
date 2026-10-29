import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import StudentRecapModal from '@/Components/StudentRecapModal';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';

const LEVELS = {
    tinggi: { label: 'Risiko tinggi', className: 'tag-danger' },
    perhatian: { label: 'Perlu perhatian', className: 'tag-warning' },
};

/**
 * Apakah orang tua sudah dikabari lewat WhatsApp untuk episode risiko ini.
 */
function ParentNotice({ notice }) {
    if (!notice) {
        return <span className="tag tag-outline risk-notice">Orang tua belum dikabari</span>;
    }

    const when = formatDateTime(notice.notifiedAt);
    const [className, text] = notice.statuses.includes('terkirim')
        ? ['tag-accent', `WA ke orang tua terkirim ${when}`]
        : notice.statuses.includes('antre')
          ? ['tag-outline', `WA dalam antrean (${when})`]
          : notice.statuses.includes('gagal')
            ? ['tag-danger', `WA gagal dikirim ${when}`]
            : notice.statuses.includes('tanpa_kontak')
              ? ['tag-warning', 'Tidak ada nomor WA orang tua']
              : notice.statuses.includes('dibatasi')
                ? ['tag-warning', 'Tertunda: batas pesan harian']
                : ['tag-neutral', `Mode uji: tercatat ${when}`];

    return <span className={`tag ${className} risk-notice`}>{text}</span>;
}

/**
 * Admin/guru BK menandai siswa sudah ditangani, dengan catatan singkat.
 */
function FollowUpDialog({ student, onClose }) {
    const form = useForm({ note: '' });

    function submit(event) {
        event.preventDefault();
        form.post(`/siswa-berisiko/${student.id}/tindak-lanjut`, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal open title={`Tandai ${student.name} sudah ditangani`} onClose={onClose}>
            <form onSubmit={submit} noValidate>
                <p className="text-muted" style={{ marginTop: 0, fontSize: 13 }}>
                    Siswa pindah ke "Sedang dipantau" dan kembali ke daftar utama hanya bila keadaannya memburuk. Saat kehadirannya membaik, datanya terhapus otomatis.
                </p>
                <Field label="Catatan tindak lanjut" htmlFor="follow_up_note" error={form.errors.note} hint='Contoh: "Orang tua ditelepon, siswa sakit tifus" atau "Dipanggil BK 3 Nov".'>
                    <textarea id="follow_up_note" className="input" rows={3} maxLength={300} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} />
                </Field>
                <div className="dialog-actions">
                    <button type="button" className="btn btn-secondary" onClick={onClose}>
                        Batal
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={form.processing || !form.data.note.trim()}>
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function EarlyWarning({ students, rules, period, classrooms, filters, homeroom, isHomeroomTeacher, whatsappEnabled, canSendWhatsApp, canFollowUp }) {
    const { auth } = usePage().props;
    const [level, setLevel] = useState('semua');
    const [tab, setTab] = useState('perlu');
    const [handling, setHandling] = useState(null);
    const [openStudent, setOpenStudent] = useState(null);
    const [sending, setSending] = useState(null);
    const [processing, setProcessing] = useState(false);

    function sendWhatsApp() {
        router.post(`/siswa-berisiko/${sending.id}/kirim-wa`, {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setSending(null);
            },
        });
    }
    const pending = students.filter((student) => !student.followUp);
    const monitored = students.filter((student) => student.followUp);
    const inTab = tab === 'perlu' ? pending : monitored;
    const high = students.filter((student) => student.level === 'tinggi').length;
    const tabHigh = inTab.filter((student) => student.level === 'tinggi').length;
    const shown = level === 'semua' ? inTab : inTab.filter((student) => student.level === level);

    return (
        <>
            <Head title="Siswa berisiko" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        Peringatan dini{homeroom ? ` · Kelas ${homeroom}` : ''}
                        {period ? ` · ${period.label}` : ''}
                    </h6>
                    <h1>Siswa berisiko</h1>
                </div>
                {classrooms.length > 0 && (
                    <div className="page-actions">
                        <select
                            className="input"
                            style={{ width: 'auto' }}
                            aria-label="Kelas"
                            value={filters.classroom ?? ''}
                            onChange={(event) => router.get('/siswa-berisiko', { classroom: event.target.value || undefined }, { preserveScroll: true, replace: true })}
                        >
                            <option value="">Semua kelas</option>
                            {classrooms.map((classroom) => (
                                <option key={classroom.id} value={classroom.id}>
                                    Kelas {classroom.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            {isHomeroomTeacher && !homeroom ? (
                <p className="alert alert-error">Anda belum ditetapkan sebagai wali kelas di tahun ajaran aktif. Hubungi admin.</p>
            ) : (
                <>
                    <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                        Sistem memeriksa absensi {period ? `${formatDate(period.from)} s.d. ${formatDate(period.to)}` : 'semester ini'} dengan aturan di bawah, yang sebagian besar hanya melihat beberapa minggu terakhir. Siswa yang sudah membaik keluar dari daftar dengan sendirinya. Daftar ini bahan tindak lanjut, bukan hukuman.
                    </p>

                    <div className="stat-strip is-compact" style={{ maxWidth: 520 }}>
                        <div className="stat status-A">
                            <div className="stat-label">Risiko tinggi</div>
                            <div className="stat-value">{high}</div>
                        </div>
                        <div className="stat status-T">
                            <div className="stat-label">Perlu perhatian</div>
                            <div className="stat-value">{students.length - high}</div>
                        </div>
                    </div>

                    <div className="early-layout">
                        <section style={{ minWidth: 0 }}>
                            <div className="toolbar" style={{ marginBottom: 12 }}>
                                <div className="seg" role="radiogroup" aria-label="Status tindak lanjut">
                                    {[
                                        ['perlu', `Perlu ditindaklanjuti (${pending.length})`],
                                        ['dipantau', `Sedang dipantau (${monitored.length})`],
                                    ].map(([value, text]) => (
                                        <label key={value} className="seg-opt">
                                            <input type="radio" name="tab" checked={tab === value} onChange={() => setTab(value)} />
                                            {text}
                                        </label>
                                    ))}
                                </div>
                                <div className="seg" role="radiogroup" aria-label="Tingkat risiko">
                                    {[
                                        ['semua', `Semua (${inTab.length})`],
                                        ['tinggi', `Tinggi (${tabHigh})`],
                                        ['perhatian', `Perhatian (${inTab.length - tabHigh})`],
                                    ].map(([value, text]) => (
                                        <label key={value} className="seg-opt">
                                            <input type="radio" name="level" checked={level === value} onChange={() => setLevel(value)} />
                                            {text}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {shown.length === 0 ? (
                                <div className="empty">
                                    <h4>Tidak ada siswa di daftar ini</h4>
                                    <p className="text-muted">{tab === 'perlu' ? 'Semua siswa berisiko sudah ditangani, atau belum ada yang memenuhi aturan peringatan dini.' : 'Belum ada siswa yang ditandai sudah ditangani.'}</p>
                                </div>
                            ) : (
                                <div className="risk-list">
                                    {shown.map((student) => (
                                        <article key={student.id} className={`risk-card is-${student.level}`}>
                                            <header className="risk-head">
                                                <div>
                                                    <b>{student.name}</b>
                                                    {student.isNew && !student.followUp && (
                                                        <>
                                                            {' '}
                                                            <span className="tag tag-accent">Baru</span>
                                                        </>
                                                    )}
                                                    <span className="cell-sub">
                                                        Kelas {student.classroom} · NIS {student.nis}
                                                    </span>
                                                </div>
                                                <span className={`tag ${LEVELS[student.level].className}`}>{LEVELS[student.level].label}</span>
                                            </header>

                                            <ul className="risk-reasons">
                                                {student.reasons.map((reason) => (
                                                    <li key={reason.text} className={`is-${reason.level}`}>
                                                        {reason.text}
                                                    </li>
                                                ))}
                                            </ul>

                                            {student.followUp && (
                                                <div className="risk-follow-up">
                                                    <b>
                                                        Sudah ditangani{student.followUp.by ? ` · ${student.followUp.by}` : ''} · {formatDateTime(student.followUp.at)}
                                                    </b>
                                                    {student.followUp.note}
                                                </div>
                                            )}

                                            <footer className="risk-foot">
                                                <span className="risk-recent" aria-label="Status 10 hari sekolah terakhir">
                                                    {student.recent.map((day) => (
                                                        <span key={day.date} className={`mark ${day.status ? `mark-${day.status}` : 'mark-empty'}`} title={`${formatDate(day.date)}: ${day.status ?? 'tidak tercatat'}`}>
                                                            {day.status ?? '·'}
                                                        </span>
                                                    ))}
                                                </span>
                                                <span className="risk-rate">
                                                    Semester: kehadiran <b>{student.rate === null ? '—' : `${student.rate}%`}</b> · A {student.counts.A} · S {student.counts.S} · I {student.counts.I}
                                                </span>
                                                {(whatsappEnabled || student.notice) && <ParentNotice notice={student.notice} />}
                                                {canSendWhatsApp && !student.contacts?.length && <span className="text-muted risk-notice">Belum ada nomor WA orang tua</span>}
                                                <span className="risk-actions">
                                                    {canFollowUp &&
                                                        (student.followUp ? (
                                                            <button type="button" className="btn btn-ghost" onClick={() => router.delete(`/siswa-berisiko/${student.id}/tindak-lanjut`, { preserveScroll: true })}>
                                                                Batalkan tanda
                                                            </button>
                                                        ) : (
                                                            <button type="button" className="btn btn-ghost" onClick={() => setHandling(student)}>
                                                                Tandai sudah ditangani
                                                            </button>
                                                        ))}
                                                    {canSendWhatsApp && student.contacts?.length > 0 && (
                                                        <button type="button" className="btn btn-ghost" onClick={() => setSending(student)}>
                                                            Kirim WA ke orang tua
                                                        </button>
                                                    )}
                                                    <button type="button" className="btn btn-ghost" onClick={() => setOpenStudent(student.id)}>
                                                        Lihat rekap
                                                    </button>
                                                </span>
                                            </footer>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </section>

                        <aside className="risk-rules">
                            <h4>Cara sistem menilai</h4>
                            <p className="text-muted" style={{ fontSize: 13 }}>
                                Siswa masuk daftar kalau memenuhi salah satu aturan ini. Satu aturan merah sudah cukup untuk "Risiko tinggi".
                            </p>
                            <ul>
                                {rules.map((rule) => (
                                    <li key={rule.text}>
                                        <span className={`tag ${LEVELS[rule.level].className}`}>{rule.level === 'tinggi' ? 'Tinggi' : 'Perhatian'}</span>
                                        {rule.text}
                                    </li>
                                ))}
                            </ul>
                            <p className="text-muted" style={{ fontSize: 13 }}>
                                Kotak kecil di tiap siswa adalah status 10 hari sekolah terakhir, dari kiri (lama) ke kanan (terbaru).
                            </p>
                        </aside>
                    </div>
                </>
            )}

            <ConfirmDialog
                open={sending !== null}
                title={`Kirim WA ke orang tua ${sending?.name}?`}
                confirmLabel={processing ? 'Mengirim…' : 'Kirim sekarang'}
                processing={processing}
                onConfirm={sendWhatsApp}
                onClose={() => !processing && setSending(null)}
            >
                <span className="cell-sub" style={{ marginBottom: 8 }}>
                    Ke: {sending?.contacts?.join(', ')}
                </span>
                <span className="wa-preview">{sending?.messagePreview}</span>
            </ConfirmDialog>

            {handling && <FollowUpDialog student={handling} onClose={() => setHandling(null)} />}

            {openStudent && <StudentRecapModal studentId={openStudent} recapLink={auth.user.role !== 'guru_bk'} onClose={() => setOpenStudent(null)} />}
        </>
    );
}

EarlyWarning.layout = (page) => <AppLayout>{page}</AppLayout>;
