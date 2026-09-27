import { useEffect, useRef } from 'react';

/**
 * Dialog di atas latar gelap. Tutup dengan Escape atau klik di luar kotak.
 */
export default function Modal({ open, title, onClose, children, width = 480 }) {
    const dialogRef = useRef(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleKey(event) {
            if (event.key === 'Escape') {
                onCloseRef.current();
            }
        }

        document.addEventListener('keydown', handleKey);
        dialogRef.current?.querySelector('input:not([type=hidden]), select, textarea, button')?.focus();

        return () => document.removeEventListener('keydown', handleKey);
    }, [open]);

    if (!open) {
        return null;
    }

    return (
        <div className="dialog-backdrop" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div ref={dialogRef} className="dialog" role="dialog" aria-modal="true" aria-label={title} style={{ width: `min(${width}px, 100%)` }}>
                <div className="dialog-title">{title}</div>
                {children}
            </div>
        </div>
    );
}
