import Modal from '@/Components/Modal';

/**
 * Minta konfirmasi sebelum tindakan yang tidak bisa dibatalkan.
 */
export default function ConfirmDialog({ open, title, children, confirmLabel = 'Ya, lanjutkan', processing = false, onConfirm, onClose }) {
    return (
        <Modal open={open} title={title} onClose={onClose}>
            <div className="dialog-body">{children}</div>
            <div className="dialog-actions">
                <button type="button" className="btn btn-secondary" onClick={onClose}>
                    Batal
                </button>
                <button type="button" className="btn btn-primary" onClick={onConfirm} disabled={processing}>
                    {processing ? 'Memproses…' : confirmLabel}
                </button>
            </div>
        </Modal>
    );
}
