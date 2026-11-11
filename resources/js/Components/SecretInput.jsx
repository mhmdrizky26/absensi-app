import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

/**
 * Kolom isian rahasia (kode tab, kode kelas, token) dengan tombol mata untuk
 * menampilkan atau menyembunyikan isinya.
 */
export default function SecretInput({ id, value, onChange, className = 'input', label = 'kode', ...props }) {
    const [shown, setShown] = useState(false);

    return (
        <div className="code-reveal">
            <input
                id={id}
                type={shown ? 'text' : 'password'}
                className={`${className} has-reveal`}
                autoComplete="off"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                value={value}
                onChange={onChange}
                {...props}
            />
            <button
                type="button"
                className="btn btn-ghost code-reveal-toggle"
                aria-label={shown ? `Sembunyikan ${label}` : `Tampilkan ${label}`}
                aria-pressed={shown}
                title={shown ? 'Sembunyikan' : 'Tampilkan'}
                onClick={() => setShown((current) => !current)}
            >
                {shown ? <EyeOff size={20} aria-hidden="true" /> : <Eye size={20} aria-hidden="true" />}
            </button>
        </div>
    );
}
