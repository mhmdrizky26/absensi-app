import { Head, router, useForm } from '@inertiajs/react';
import { Check, Clock, Paperclip, X } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import StudentPicker from '@/Components/StudentPicker';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';

const todayIso = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

function dateRange(from, to) {
    return from === to ? formatDate(from) : `${formatDate(from)} – ${formatDate(to)}`;
}

function ApprovalStep({ label, approval, rejected }) {
    return (
        <div className={`approval-step ${approval ? 'is-done' : rejected ? 'is-stopped' : ''}`}>
            {approval ? <Check size={14} aria-hidden="true" /> : <Clock size={14} aria-hidden="true" />}
            <span>
                <b>{label}</b>
                <span className="cell-sub">{approval ? `${approval.by ?? '—'} · ${formatDateTime(approval.at)}` : rejected ? 'Tidak diproses' : 'Menunggu'}</span>
            </span>
        </div>
    );
}

function DispensationCard({ dispensation, onApprove, onReject, onCancel }) {
    return (
        <article className={`dispensation-card is-${dispensation.status}`}>
            <header>
                <div>
                    <div className="dispensation-student">{dispensation.student.name}</div>
                    <div className="cell-sub">
                        Kelas {dispensation.classroom} · NIS {dispensation.student.nis}
                    </div>
                </div>
                <span className={`tag ${dispensation.status === 'disetujui' ? 'tag-accent' : dispensation.status === 'ditolak' ? 'tag-danger' : 'tag-outline'}`}>{dispensation.statusLabel}</span>
            </header>

            <p className="dispensation-reason">
                <b>{dateRange(dispensation.from, dispensation.to)}</b> · {dispensation.reason}
                {dispensation.hasAttachment && (
                    <>
                        {' · '}
                        <a href={`/dispensasi/${dispensation.id}/surat`} target="_blank" rel="noreferrer">
                            <Paperclip size={12} aria-hidden="true" /> surat tugas
                        </a>
                    </>
                )}
            </p>

            <div className="approval-steps">
                <ApprovalStep label="Wali kelas" approval={dispensation.homeroomApproval} rejected={dispensation.rejection} />
                <ApprovalStep label="Guru piket" approval={dispensation.dutyApproval} rejected={dispensation.rejection} />
            </div>

            {!dispensation.hasHomeroomTeacher && dispensation.status === 'menunggu' && <p className="alert alert-error">Kelas ini belum punya wali kelas. Minta admin menentukan wali kelasnya.</p>}

            {dispensation.rejection && (
                <p className="dispensation-rejection">
                    Ditolak oleh {dispensation.rejection.by ?? '—'}: “{dispensation.rejection.reason}”
                </p>
            )}

            <footer>
                <span className="cell-sub">
                    Diajukan {dispensation.requester ? `oleh ${dispensation.requester}` : ''} · {formatDateTime(dispensation.createdAt)}
                </span>
                <span className="dispensation-actions">
                    {dispensation.canCancel && (
                        <button type="button" className="btn btn-ghost" onClick={() => onCancel(dispensation)}>
                            Batalkan
                        </button>
                    )}
                    {dispensation.canDecide && (
                        <>
                            <button type="button" className="btn btn-secondary" onClick={() => onReject(dispensation)}>
                                <X size={16} aria-hidden="true" /> Tolak
                            </button>
                            <button type="button" className="btn btn-primary" onClick={() => onApprove(dispensation)}>
                                <Check size={16} aria-hidden="true" /> Setujui
                            </button>
                        </>
                    )}
                </span>
            </footer>
        </article>
    );
}

export default function Dispensations({ awaiting, others, homeroom }) {
    const [student, setStudent] = useState(null);
    const [fileKey, setFileKey] = useState(0);
    const [approving, setApproving] = useState(null);
    const [rejecting, setRejecting] = useState(null);
    const [cancelling, setCancelling] = useState(null);
    const form = useForm({ student_id: '', from: todayIso(), to: todayIso(), reason: '', attachment: null });
    const rejectForm = useForm({ rejection_reason: '' });

    function chooseStudent(chosen) {
        setStudent(chosen);
        form.setData('student_id', chosen?.id ?? '');
    }

    function submit(event) {
        event.preventDefault();
        form.post('/dispensasi', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setStudent(null);
                setFileKey((key) => key + 1);
            },
        });
    }

    function approve() {
        router.post(`/dispensasi/${approving.id}/setujui`, {}, { preserveScroll: true, onFinish: () => setApproving(null) });
    }

    function reject(event) {
        event.preventDefault();
        rejectForm.post(`/dispensasi/${rejecting.id}/tolak`, { preserveScroll: true, onSuccess: () => setRejecting(null) });
    }

    function cancel() {
        router.delete(`/dispensasi/${cancelling.id}`, { preserveScroll: true, onFinish: () => setCancelling(null) });
    }

    const cardHandlers = {
        onApprove: setApproving,
        onReject: (dispensation) => {
            rejectForm.reset();
            rejectForm.clearErrors();
            setRejecting(dispensation);
        },
        onCancel: setCancelling,
    };

    return (
        <>
            <Head title="Dispensasi" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">{homeroom ? `Wali kelas ${homeroom}` : 'Kegiatan sekolah di luar kelas'}</h6>
                    <h1>Dispensasi</h1>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Dispensasi untuk siswa yang mewakili sekolah (lomba, upacara, tugas OSIS, …) dihitung <b>sama dengan hadir</b>. Pengajuan baru berlaku setelah disetujui <b>wali kelas</b> siswa itu <b>dan guru piket</b>.
            </p>

            <div className="excuse-layout">
                <form onSubmit={submit} noValidate className="excuse-form">
                    <h4 style={{ margin: 0 }}>Ajukan dispensasi</h4>

                    <Field label="Siswa" htmlFor="student" error={form.errors.student_id} hint={homeroom ? `Wali kelas hanya bisa memilih siswa kelas ${homeroom}.` : undefined}>
                        <StudentPicker id="student" value={student} onChange={chooseStudent} error={form.errors.student_id} date={form.data.from} placeholder={homeroom ? `Cari siswa kelas ${homeroom}` : undefined} />
                    </Field>

                    <div className="form-grid">
                        <Field label="Dari tanggal" htmlFor="from" error={form.errors.from}>
                            <input id="from" type="date" className="input" value={form.data.from} onChange={(event) => form.setData((data) => ({ ...data, from: event.target.value, to: data.to < event.target.value ? event.target.value : data.to }))} />
                        </Field>
                        <Field label="Sampai tanggal" htmlFor="to" error={form.errors.to}>
                            <input id="to" type="date" className="input" min={form.data.from} value={form.data.to} onChange={(event) => form.setData('to', event.target.value)} />
                        </Field>
                    </div>

                    <Field label="Kegiatan" htmlFor="reason" error={form.errors.reason} hint="Contoh: Lomba cerdas cermat tingkat kabupaten">
                        <input id="reason" className="input" maxLength={200} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    </Field>

                    <Field label="Surat tugas (opsional)" htmlFor="attachment" error={form.errors.attachment} hint="JPG, PNG, atau PDF, maks. 4 MB">
                        <input key={fileKey} id="attachment" type="file" className="input" accept="image/*,.pdf" onChange={(event) => form.setData('attachment', event.target.files[0] ?? null)} />
                    </Field>

                    <div>
                        <button type="submit" className="btn btn-primary" disabled={form.processing || !student}>
                            {form.processing ? 'Mengirim…' : 'Ajukan'}
                        </button>
                    </div>
                </form>

                <section style={{ minWidth: 0 }}>
                    <h4>Perlu persetujuan Anda ({awaiting.length})</h4>
                    {awaiting.length === 0 ? (
                        <p className="text-muted">Tidak ada pengajuan yang menunggu keputusan Anda.</p>
                    ) : (
                        <div className="dispensation-list">
                            {awaiting.map((dispensation) => (
                                <DispensationCard key={dispensation.id} dispensation={dispensation} {...cardHandlers} />
                            ))}
                        </div>
                    )}

                    <h4 style={{ marginTop: 32 }}>Pengajuan lain</h4>
                    {others.length === 0 ? (
                        <p className="text-muted">Belum ada pengajuan dispensasi dalam 60 hari terakhir.</p>
                    ) : (
                        <div className="dispensation-list">
                            {others.map((dispensation) => (
                                <DispensationCard key={dispensation.id} dispensation={dispensation} {...cardHandlers} />
                            ))}
                        </div>
                    )}
                </section>
            </div>

            <ConfirmDialog open={approving !== null} title={`Setujui dispensasi ${approving?.student.name}?`} confirmLabel="Setujui" onConfirm={approve} onClose={() => setApproving(null)}>
                {approving && `${dateRange(approving.from, approving.to)} · ${approving.reason}. Setelah wali kelas dan guru piket sama-sama setuju, hari-hari sekolah itu dicatat Dispensasi dan dihitung hadir.`}
            </ConfirmDialog>

            <Modal open={rejecting !== null} title={`Tolak dispensasi ${rejecting?.student.name}?`} onClose={() => setRejecting(null)}>
                <form onSubmit={reject} noValidate>
                    <Field label="Alasan penolakan" htmlFor="rejection_reason" error={rejectForm.errors.rejection_reason}>
                        <input id="rejection_reason" className="input" maxLength={200} value={rejectForm.data.rejection_reason} onChange={(event) => rejectForm.setData('rejection_reason', event.target.value)} />
                    </Field>
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={() => setRejecting(null)}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={rejectForm.processing}>
                            Tolak dispensasi
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog open={cancelling !== null} title="Batalkan pengajuan ini?" confirmLabel="Batalkan pengajuan" onConfirm={cancel} onClose={() => setCancelling(null)}>
                Pengajuan dan surat tugasnya dihapus.
            </ConfirmDialog>
        </>
    );
}

Dispensations.layout = (page) => <AppLayout>{page}</AppLayout>;
