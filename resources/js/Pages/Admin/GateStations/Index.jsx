import { Head, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Plus } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import SecretInput from '@/Components/SecretInput';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/format';

function StationStatus({ station }) {
    if (station.isRevoked) {
        return <span className="tag tag-outline">Dicabut</span>;
    }

    return <span className={`tag ${station.isOnline ? 'tag-neutral' : 'tag-outline'}`}>{station.isOnline ? 'Online' : 'Offline'}</span>;
}

export default function GateStationsIndex({ stations, suggestion, network }) {
    const [editing, setEditing] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const [showCodes, setShowCodes] = useState(false);
    const form = useForm({ name: '', code: '' });
    const networkForm = useForm({ allowed_ips: network.allowedIps });

    function openCreate() {
        form.clearErrors();
        form.setData(suggestion);
        setEditing('new');
    }

    function openEdit(station) {
        form.clearErrors();
        form.setData({ name: station.name, code: station.code });
        setEditing(station);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/tab-gerbang', options);
        } else {
            form.put(`/tab-gerbang/${editing.id}`, options);
        }
    }

    function confirm() {
        const { action, station } = confirming;
        const options = { preserveScroll: true, onFinish: () => setConfirming(null) };

        if (action === 'revoke') {
            router.post(`/tab-gerbang/${station.id}/cabut`, {}, options);
        } else if (action === 'restore') {
            router.delete(`/tab-gerbang/${station.id}/cabut`, options);
        } else {
            router.delete(`/tab-gerbang/${station.id}`, options);
        }
    }

    function submitNetwork(event) {
        event.preventDefault();
        networkForm.put('/jaringan-gerbang', { preserveScroll: true });
    }

    function useCurrentIp() {
        const ips = networkForm.data.allowed_ips.split(/[\s,]+/).filter(Boolean);

        if (!ips.includes(network.currentIp)) {
            networkForm.setData('allowed_ips', [...ips, network.currentIp].join('\n'));
        }
    }

    const codeChanged = editing !== 'new' && editing && form.data.code.trim().toUpperCase() !== editing.code;
    const confirmText = {
        revoke: {
            title: `Cabut ${confirming?.station.name}?`,
            label: 'Cabut',
            body: 'Tab itu langsung keluar dan kodenya tidak bisa dipakai login. Pakai ini kalau tab hilang atau rusak. Scan yang sudah tercatat tidak berubah.',
        },
        restore: {
            title: `Aktifkan lagi ${confirming?.station.name}?`,
            label: 'Aktifkan',
            body: `Kode ${confirming?.station.code} bisa dipakai lagi. Tab perlu login ulang dengan kode itu.`,
        },
        delete: {
            title: `Hapus ${confirming?.station.name}?`,
            label: 'Hapus',
            body: 'Tab dihapus dan kodenya tidak bisa dipakai lagi. Absensi yang sudah di-scan lewat tab ini tetap tersimpan.',
        },
    }[confirming?.action ?? 'delete'];

    return (
        <>
            <Head title="Tab gerbang" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Absensi gerbang</h6>
                    <h1>Tab gerbang</h1>
                </div>
                <div className="page-actions">
                    <button type="button" className="btn btn-secondary" onClick={() => setShowCodes((shown) => !shown)}>
                        {showCodes ? <EyeOff size={16} aria-hidden="true" /> : <Eye size={16} aria-hidden="true" />}
                        {showCodes ? 'Sembunyikan kode' : 'Tampilkan kode'}
                    </button>
                    <button type="button" className="btn btn-primary" onClick={openCreate}>
                        <Plus size={16} aria-hidden="true" /> Tambah tab
                    </button>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Tab login dengan kode tab setiap pagi sebelum jam masuk dan keluar sendiri saat jam masuk lewat, saat absen diselesaikan, atau saat dicabut; setelah itu kode tab tidak bisa dipakai lagi sampai besok. Siswa scan sendiri di antara jam buka dan jam masuk di Pengaturan.
            </p>

            {stations.length === 0 ? (
                <div className="empty">
                    <h4>Belum ada tab</h4>
                    <p className="text-muted">Tambahkan satu tab untuk setiap tablet di gerbang, misalnya Tab 1 dengan kode TAB1.</p>
                </div>
            ) : (
                <div className="table-wrap">
                    <table className="table" style={{ minWidth: 640 }}>
                        <thead>
                            <tr>
                                <th>Tab</th>
                                <th style={{ width: 120 }}>Kode</th>
                                <th style={{ width: 100 }}>Status</th>
                                <th style={{ width: 170 }}>Terakhir aktif</th>
                                <th style={{ width: 130 }}>Belum terkirim</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {stations.map((station) => (
                                <tr key={station.id}>
                                    <td style={{ fontWeight: 600 }}>{station.name}</td>
                                    <td>
                                        <span className="mono">{showCodes ? station.code : '••••'}</span>
                                    </td>
                                    <td>
                                        <StationStatus station={station} />
                                    </td>
                                    <td className="text-muted" style={{ fontSize: 13 }}>
                                        {station.lastSeenAt ? formatDateTime(station.lastSeenAt) : 'Belum pernah'}
                                    </td>
                                    <td style={station.pendingScans > 0 ? { fontWeight: 700 } : undefined}>{station.pendingScans > 0 ? `${station.pendingScans} scan` : '—'}</td>
                                    <td className="cell-actions">
                                        <button type="button" className="btn btn-ghost" onClick={() => openEdit(station)}>
                                            Ubah
                                        </button>
                                        <button type="button" className="btn btn-ghost" onClick={() => setConfirming({ action: station.isRevoked ? 'restore' : 'revoke', station })}>
                                            {station.isRevoked ? 'Aktifkan' : 'Cabut'}
                                        </button>
                                        <button type="button" className="btn btn-ghost" onClick={() => setConfirming({ action: 'delete', station })}>
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <form onSubmit={submitNetwork} noValidate className="excuse-form" style={{ marginTop: 40, maxWidth: 560 }}>
                <h4 style={{ margin: 0 }}>Batasi ke jaringan sekolah</h4>
                <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                    Kalau diisi, tab hanya bisa login dan scan dari alamat IP ini, jadi kode tab tidak bisa dipakai dari rumah. Kosongkan untuk mematikan batas ini. Buka halaman ini dari WiFi sekolah lalu tekan "Pakai IP saya".
                </p>
                <Field label="Alamat IP sekolah" htmlFor="allowed_ips" error={networkForm.errors.allowed_ips} hint={`IP Anda sekarang: ${network.currentIp}. Satu alamat per baris; rentang seperti 10.0.0.0/24 juga bisa.`}>
                    <textarea id="allowed_ips" className="input" rows={3} value={networkForm.data.allowed_ips} onChange={(event) => networkForm.setData('allowed_ips', event.target.value)} />
                </Field>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <button type="button" className="btn btn-secondary" onClick={useCurrentIp}>
                        Pakai IP saya
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={networkForm.processing}>
                        Simpan
                    </button>
                </div>
            </form>

            <Modal open={editing !== null} title={editing === 'new' ? 'Tambah tab' : `Ubah ${editing?.name}`} onClose={() => setEditing(null)}>
                <form onSubmit={submit} noValidate>
                    <div className="form-grid">
                        <Field label="Nama tab" htmlFor="name" error={form.errors.name}>
                            <input id="name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        </Field>
                        <Field label="Kode" htmlFor="code" error={form.errors.code} hint="Dipakai untuk login di tab">
                            <SecretInput id="code" label="kode tab" className="input mono" value={form.data.code} autoCapitalize="characters" onChange={(event) => form.setData('code', event.target.value.toUpperCase().replace(/\s+/g, ''))} />
                        </Field>
                    </div>
                    {codeChanged && (
                        <p className="alert" style={{ margin: 0 }}>
                            Setelah kode diganti, {editing.name} langsung keluar dan perlu login lagi dengan kode baru.
                        </p>
                    )}
                    <div className="dialog-actions">
                        <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>
                            Batal
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog open={confirming !== null} title={confirmText.title} confirmLabel={confirmText.label} onConfirm={confirm} onClose={() => setConfirming(null)}>
                {confirmText.body}
            </ConfirmDialog>
        </>
    );
}

GateStationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
