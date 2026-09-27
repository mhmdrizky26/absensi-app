import { useEffect, useRef, useState } from 'react';
import { getJson } from '@/lib/http';

/**
 * Cari siswa aktif berdasarkan nama/NIS/NISN lalu pilih satu. Wali kelas
 * hanya menemukan siswa kelasnya sendiri (dibatasi di server).
 */
export default function StudentPicker({ id, value, onChange, placeholder = 'Ketik nama atau NIS', error }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const requestId = useRef(0);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);

            return;
        }

        const current = ++requestId.current;
        setLoading(true);
        const timer = setTimeout(async () => {
            try {
                const { students } = await getJson(`/cari-siswa?q=${encodeURIComponent(query.trim())}`);

                if (current === requestId.current) {
                    setResults(students);
                    setOpen(true);
                }
            } catch {
                setResults([]);
            } finally {
                if (current === requestId.current) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => clearTimeout(timer);
    }, [query]);

    function choose(student) {
        onChange(student);
        setQuery('');
        setResults([]);
        setOpen(false);
    }

    if (value) {
        return (
            <div className="picker-chosen">
                <span>
                    <b>{value.name}</b>
                    <span className="cell-sub">
                        NIS {value.nis} · Kelas {value.classroom}
                    </span>
                </span>
                <button type="button" className="btn btn-ghost" onClick={() => onChange(null)}>
                    Ganti
                </button>
            </div>
        );
    }

    return (
        <div className="picker">
            <input
                id={id}
                type="search"
                className="input"
                autoComplete="off"
                placeholder={placeholder}
                value={query}
                aria-invalid={error ? 'true' : undefined}
                onChange={(event) => setQuery(event.target.value)}
                onFocus={() => results.length > 0 && setOpen(true)}
            />
            {open && query.trim().length >= 2 && (
                <div className="picker-results" role="listbox">
                    {loading && results.length === 0 && <div className="picker-empty">Mencari…</div>}
                    {!loading && results.length === 0 && <div className="picker-empty">Siswa tidak ditemukan.</div>}
                    {results.map((student) => (
                        <button key={student.id} type="button" role="option" className="picker-option" onClick={() => choose(student)}>
                            <span>
                                {student.name}
                                <span className="cell-sub">NIS {student.nis}</span>
                            </span>
                            <b>{student.classroom}</b>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
