/**
 * Label + kontrol + pesan error, memakai kelas .field dari design system.
 */
export default function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div className="field">
            <label htmlFor={htmlFor}>{label}</label>
            {children}
            {hint && !error && <div className="field-hint">{hint}</div>}
            {error && <div className="field-error">{error}</div>}
        </div>
    );
}
