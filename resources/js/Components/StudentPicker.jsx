import { useEffect, useRef, useState } from 'react';
import { getJson } from '@/lib/http';

/**
 * Cari siswa aktif berdasarkan nama/NIS/NISN lalu pilih satu. Wali kelas
 * hanya menemukan siswa kelasnya sendiri (dibatasi di server). Dengan `date`,
 * siswa yang sudah izin/sakit/dispensasi pada tanggal itu tidak ditampilkan.
 */
export default function StudentPicker({ id, value, onChange, placeholder = 'Ketik nama atau NIS', error, date }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [hidden, setHidden] = useState(0);
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
                const params = new URLSearchParams({ q: query.trim(), ...(date ? { date } : {}) });
                const { students, hidden: hiddenCount } = await getJson(`/cari-siswa?${params}`);

                if (current === requestId.current) {
                    setResults(students);
                    setHidden(hiddenCount ?? 0);
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
    }, [query, date]);

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
                    {!loading && results.length === 0 && hidden === 0 && <div className="picker-empty">Siswa tidak ditemukan.</div>}
                    {results.map((student) => (
                        <button key={student.id} type="button" role="option" className="picker-option" onClick={() => choose(student)}>
                            <span>
                                {student.name}
                                <span className="cell-sub">NIS {student.nis}</span>
                            </span>
                            <b>{student.classroom}</b>
                        </button>
                    ))}
                    {!loading && hidden > 0 && <div className="picker-empty">{hidden} siswa tidak ditampilkan karena sudah tercatat izin, sakit, atau dispensasi pada tanggal itu.</div>}
                </div>
            )}
        </div>
    );
}
