import { CheckCircle2, XCircle } from 'lucide-react';
import Modal from '@/Components/Modal';

/**
 * Hasil menyimpan (berhasil atau gagal) sebagai dialog, supaya pengguna
 * pasti melihatnya. Tutup dengan tombol Oke, Escape, atau klik di luar.
 */
export default function NoticeDialog({ notice, onClose }) {
    const isError = notice?.kind === 'error';
    const Icon = isError ? XCircle : CheckCircle2;

    return (
        <Modal open={notice !== null} title={isError ? 'Gagal' : 'Berhasil'} onClose={onClose} width={420}>
            <div className={`notice-dialog ${isError ? 'is-error' : 'is-success'}`} role={isError ? 'alert' : 'status'}>
                <Icon size={28} aria-hidden="true" />
                <p>{notice?.message}</p>
            </div>
            <div className="dialog-actions">
                <button type="button" className="btn btn-primary" onClick={onClose}>
                    Oke
                </button>
            </div>
        </Modal>
    );
}
