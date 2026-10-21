import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';

const STATUS_TAGS = {
    terkirim: 'tag-accent',
    gagal: 'tag-danger',
    antre: 'tag-outline',
    uji: 'tag-neutral',
    tanpa_kontak: 'tag-warning',
    dibatasi: 'tag-warning',
};

function Toggle({ id, label, hint, checked, onChange }) {
    return (
        <label className="wa-toggle" htmlFor={id}>
            <input id={id} type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>
                <b>{label}</b>
                {hint && <span className="cell-sub">{hint}</span>}
            </span>
        </label>
    );
}

const SAMPLE = {
    nama: 'Ayu Lestari',
    kelas: 'VII-A',
    alasan: 'Alpa 3 hari berturut-turut (s.d. 19 Okt). Alpa 6 kali semester ini.',
    wali_kelas: 'Dirja Uyainah, S.Pd.',
};

/** Isi penanda dengan contoh data, sama seperti yang dilakukan server. */
function fillSample(text, schoolName) {
    const values = { ...SAMPLE, sekolah: schoolName };

    return text.replace(/\{(nama|kelas|alasan|wali_kelas|sekolah)\}/gi, (_, key) => values[key.toLowerCase()]).replace(/\.+/g, (dots) => (dots.length === 2 ? '.' : dots));
}

function TextEditor({ id, label, hint, value, defaultValue, error, placeholders, schoolName, onChange }) {
    return (
        <div className="wa-text">
            <Field label={label} htmlFor={id} error={error} hint={hint}>
                <textarea id={id} className="input" rows={4} maxLength={1000} value={value} onChange={(event) => onChange(event.target.value)} />
            </Field>
            <div className="wa-text-tools">
                {placeholders.map((placeholder) => (
                    <button key={placeholder} type="button" className="btn btn-ghost wa-chip" onClick={() => onChange(`${value}{${placeholder}}`)}>
                        {`{${placeholder}}`}
                    </button>
                ))}
                {value !== defaultValue && (
                    <button type="button" className="btn btn-ghost" style={{ marginLeft: 'auto' }} onClick={() => onChange(defaultValue)}>
                        Kembalikan ke bawaan
                    </button>
                )}
            </div>
            <span className="wa-preview">{fillSample(value, schoolName)}</span>
        </div>
    );
}

export default function WhatsApp({ settings, texts, defaultTexts, placeholders, schoolName, lastRunOn, connection, stats, messages, filters, statuses }) {
    const textForm = useForm(texts);

    function submitTexts(event) {
        event.preventDefault();
        textForm.put('/notifikasi-wa/pesan', { preserveScroll: true });
    }

    const form = useForm(settings);
    const testForm = useForm({ phone: '' });
    const [confirmRun, setConfirmRun] = useState(false);

    function submit(event) {
        event.preventDefault();
        form.put('/notifikasi-wa', { preserveScroll: true });
    }

    function sendTest(event) {
        event.preventDefault();
        testForm.post('/notifikasi-wa/uji', { preserveScroll: true });
    }

    function filterStatus(status) {
        router.get('/notifikasi-wa', status ? { status } : {}, { preserveScroll: true, preserveState: true, only: ['messages', 'filters'] });
    }

    return (
        <>
            <Head title="Notifikasi WhatsApp" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        <Link href="/pengaturan">Pengaturan</Link> · Orang tua siswa berisiko
                    </h6>
                    <h1>Notifikasi WhatsApp</h1>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Setiap hari sekolah pada jam pemeriksaan, sistem membandingkan daftar siswa berisiko dengan hari sebelumnya. Orang tua dikabari sekali saat anaknya masuk daftar, dan dikabari lagi bila naik ke risiko tinggi atau alpa beruntun lagi. Siswa yang keluar
                dari daftar lalu masuk lagi dikabari ulang. Data yang dihitung hanya sampai kemarin, supaya wali kelas sempat mengisi surat sakit/izin.
            </p>

            {connection.configured ? (
                <p className="alert" role="status">
                    Terhubung lewat <b>Fonnte</b>. Pesan dikirim dari nomor WhatsApp yang ditautkan di fonnte.com; pakai nomor cadangan karena nomor bisa diblokir WhatsApp.
                </p>
            ) : (
                <p className="alert alert-error" role="alert">
                    Belum terhubung. Isi <code>FONNTE_TOKEN</code> di file <code>.env</code> dengan token device dari fonnte.com (setelah nomor WA tersambung lewat QR), lalu muat ulang halaman ini.
                </p>
            )}

            {form.data.test_mode && form.data.enabled && <p className="alert">Mode uji aktif: pemeriksaan tetap berjalan, tetapi pesan hanya dicatat di log dan tidak dikirim ke orang tua.</p>}

            <div className="stat-strip is-compact" style={{ marginBottom: 32 }}>
                <div className="stat">
                    <div className="stat-label">Nomor orang tua (setuju)</div>
                    <div className="stat-value">{stats.contacts}</div>
                    <div className="stat-delta">{stats.studentsWithContact} siswa</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Pesan terkirim</div>
                    <div className="stat-value">{stats.sent}</div>
                </div>
                <div className="stat status-A">
                    <div className="stat-label">Pesan gagal</div>
                    <div className="stat-value">{stats.failed}</div>
                </div>
                <div className="stat">
                    <div className="stat-label">Pemeriksaan terakhir</div>
                    <div className="stat-value" style={{ fontSize: 22 }}>
                        {lastRunOn ? formatDate(lastRunOn) : '—'}
                    </div>
                </div>
            </div>

            <div className="settings-layout">
                <form onSubmit={submit} noValidate className="excuse-form">
                    <h4 style={{ margin: 0 }}>Pengaturan</h4>
                    <Toggle id="enabled" label="Nyalakan notifikasi" hint="Pemeriksaan harian berjalan otomatis pada jam di bawah." checked={form.data.enabled} onChange={(value) => form.setData('enabled', value)} />
                    <Toggle id="test_mode" label="Mode uji" hint="Pesan hanya dicatat, tidak dikirim. Matikan setelah daftar di log terlihat benar." checked={form.data.test_mode} onChange={(value) => form.setData('test_mode', value)} />
                    <div className="form-grid">
                        <Field label="Jam pemeriksaan" htmlFor="send_at" error={form.errors.send_at} hint="Hari sekolah saja.">
                            <input id="send_at" type="time" className="input" value={form.data.send_at} onChange={(event) => form.setData('send_at', event.target.value)} />
                        </Field>
                        <Field label="Batas pesan per hari" htmlFor="daily_limit" error={form.errors.daily_limit} hint="Pengaman bila ada data aneh.">
                            <input id="daily_limit" type="number" min={1} max={1000} className="input" value={form.data.daily_limit} onChange={(event) => form.setData('daily_limit', event.target.value)} />
                        </Field>
                    </div>
                    <Toggle id="on_escalation" label="Kabari lagi saat naik ke risiko tinggi" checked={form.data.on_escalation} onChange={(value) => form.setData('on_escalation', value)} />
                    <Toggle id="on_new_streak" label="Kabari lagi saat alpa beruntun lagi" hint="Walau siswa masih di daftar berisiko." checked={form.data.on_new_streak} onChange={(value) => form.setData('on_new_streak', value)} />
                    <div>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan pengaturan
                        </button>
                    </div>
                </form>

                <section className="excuse-form">
                    <h4 style={{ margin: 0 }}>Uji sambungan</h4>
                    <form onSubmit={sendTest} noValidate style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        <Field label="Kirim pesan uji ke" htmlFor="test_phone" error={testForm.errors.phone} hint="Mengirim pesan teks singkat lewat Fonnte untuk memastikan nomor pengirim tersambung.">
                            <input id="test_phone" className="input" inputMode="tel" placeholder="0812 3456 7890" value={testForm.data.phone} onChange={(event) => testForm.setData('phone', event.target.value)} />
                        </Field>
                        <div>
                            <button type="submit" className="btn btn-secondary" disabled={testForm.processing || !connection.configured}>
                                {testForm.processing ? 'Mengirim…' : 'Kirim pesan uji'}
                            </button>
                        </div>
                    </form>

                    <div style={{ borderTop: '1px solid var(--color-divider)', paddingTop: 16 }}>
                        <p className="text-muted" style={{ fontSize: 13, marginTop: 0 }}>
                            Jalankan pemeriksaan hari ini sekarang tanpa menunggu jamnya. Mengikuti mode uji di samping.
                        </p>
                        <button type="button" className="btn btn-secondary" onClick={() => setConfirmRun(true)}>
                            Jalankan pemeriksaan sekarang
                        </button>
                    </div>

                </section>
            </div>

            <form onSubmit={submitTexts} noValidate className="excuse-form" style={{ marginTop: 40 }}>
                <h4 style={{ margin: 0 }}>Isi pesan</h4>
                <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                    Penanda di dalam kurung kurawal diisi otomatis untuk tiap siswa: <code>{'{nama}'}</code> nama siswa, <code>{'{kelas}'}</code> kelas, <code>{'{alasan}'}</code> alasan masuk daftar berisiko, <code>{'{wali_kelas}'}</code> wali kelas,{' '}
                    <code>{'{sekolah}'}</code> nama sekolah. Kotak hijau menunjukkan contoh hasilnya.
                </p>
                <div className="wa-text-grid">
                    <TextEditor id="text_high" label="Pesan risiko tinggi" value={textForm.data.high} defaultValue={defaultTexts.high} error={textForm.errors.high} placeholders={placeholders} schoolName={schoolName} onChange={(value) => textForm.setData('high', value)} />
                    <TextEditor id="text_watch" label="Pesan perlu perhatian" value={textForm.data.watch} defaultValue={defaultTexts.watch} error={textForm.errors.watch} placeholders={placeholders} schoolName={schoolName} onChange={(value) => textForm.setData('watch', value)} />
                    <TextEditor
                        id="text_test"
                        label="Pesan uji"
                        hint="Dikirim lewat tombol Kirim pesan uji."
                        value={textForm.data.test}
                        defaultValue={defaultTexts.test}
                        error={textForm.errors.test}
                        placeholders={['sekolah']}
                        schoolName={schoolName}
                        onChange={(value) => textForm.setData('test', value)}
                    />
                </div>
                <div>
                    <button type="submit" className="btn btn-primary" disabled={textForm.processing || !textForm.isDirty}>
                        {textForm.processing ? 'Menyimpan…' : 'Simpan isi pesan'}
                    </button>
                </div>
            </form>

            <section style={{ marginTop: 40 }}>
                <div className="toolbar">
                    <h4 style={{ margin: 0, marginRight: 'auto' }}>Log pesan</h4>
                    <select className="input" style={{ width: 'auto' }} aria-label="Status" value={filters.status} onChange={(event) => filterStatus(event.target.value)}>
                        <option value="">Semua status</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>

                {messages.data.length === 0 ? (
                    <div className="empty">
                        <h4>Belum ada pesan</h4>
                        <p className="text-muted">Pesan muncul di sini setelah pemeriksaan harian berjalan.</p>
                    </div>
                ) : (
                    <div className="table-wrap">
                        <table className="table" style={{ minWidth: 860 }}>
                            <thead>
                                <tr>
                                    <th style={{ width: 130 }}>Waktu</th>
                                    <th>Siswa</th>
                                    <th>Alasan</th>
                                    <th style={{ width: 140 }}>Nomor</th>
                                    <th style={{ width: 170 }}>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {messages.data.map((message) => (
                                    <tr key={message.id}>
                                        <td>
                                            {formatDateTime(message.createdAt)}
                                            <span className="cell-sub">{message.trigger}</span>
                                        </td>
                                        <td>
                                            <span style={{ fontWeight: 600 }}>{message.student}</span>
                                            <span className="cell-sub">
                                                {message.classroom ? `Kelas ${message.classroom} · ` : ''}NIS {message.nis}
                                            </span>
                                        </td>
                                        <td>
                                            <span className={`tag ${message.level === 'tinggi' ? 'tag-danger' : 'tag-warning'}`}>{message.level === 'tinggi' ? 'Tinggi' : 'Perhatian'}</span>
                                            <span className="cell-sub">{message.reason}</span>
                                        </td>
                                        <td>{message.phone ?? <span className="text-muted">—</span>}</td>
                                        <td>
                                            <span className={`tag ${STATUS_TAGS[message.status] ?? 'tag-neutral'}`}>{message.statusLabel}</span>
                                            {message.error && <span className="cell-sub" style={{ color: 'var(--color-danger)' }}>{message.error}</span>}
                                        </td>
                                        <td className="cell-actions">
                                            {message.canResend && (
                                                <button type="button" className="btn btn-ghost" disabled={!connection.configured} onClick={() => router.post(`/notifikasi-wa/pesan/${message.id}/kirim-ulang`, {}, { preserveScroll: true })}>
                                                    Kirim {message.status === 'uji' ? 'sungguhan' : 'ulang'}
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination paginator={messages} />
            </section>

            <ConfirmDialog
                open={confirmRun}
                title="Jalankan pemeriksaan sekarang?"
                confirmLabel="Jalankan"
                onConfirm={() => router.post('/notifikasi-wa/jalankan', {}, { preserveScroll: true, onFinish: () => setConfirmRun(false) })}
                onClose={() => setConfirmRun(false)}
            >
                {settings.test_mode
                    ? 'Mode uji aktif: siswa berisiko akan dicatat dan pesan hanya ditulis di log, tidak dikirim.'
                    : 'Mode uji mati: orang tua siswa yang baru berisiko akan langsung dikirimi pesan WhatsApp.'}
            </ConfirmDialog>
        </>
    );
}

WhatsApp.layout = (page) => <AppLayout>{page}</AppLayout>;
