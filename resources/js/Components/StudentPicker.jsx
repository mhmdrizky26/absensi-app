import { useEffect, useRef, useState } from 'react';
import { getJson } from '@/lib/http';

/**
 * Pilih satu siswa aktif. Begitu kolom diklik, daftar siswa langsung muncul
 * (wali kelas: seluruh kelasnya), lalu menyempit sambil guru mengetik nama/NIS.
 * Wali kelas hanya menemukan siswa kelasnya sendiri (dibatasi di server).
 * Dengan `date`, siswa yang sudah izin/sakit/dispensasi pada tanggal itu tidak ditampilkan.
 */
export default function StudentPicker({ id, value, onChange, placeholder = 'Klik untuk melihat siswa, atau ketik nama/NIS', error, date }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [total, setTotal] = useState(0);
    const [hidden, setHidden] = useState(0);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const requestId = useRef(0);

    useEffect(() => {
        if (!open) {
            return;
        }

        const current = ++requestId.current;
        setLoading(true);
        const timer = setTimeout(
            async () => {
                try {
                    const params = new URLSearchParams({ q: query.trim(), ...(date ? { date } : {}) });
                    const response = await getJson(`/cari-siswa?${params}`);

                    if (current === requestId.current) {
                        setResults(response.students);
                        setTotal(response.total ?? response.students.length);
                        setHidden(response.hidden ?? 0);
                    }
                } catch {
                    if (current === requestId.current) {
                        setResults([]);
                    }
                } finally {
                    if (current === requestId.current) {
                        setLoading(false);
                    }
                }
            },
            query.trim() === '' ? 0 : 250,
        );

        return () => clearTimeout(timer);
    }, [open, query, date]);

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

    const searching = query.trim() !== '';

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
                aria-expanded={open}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onClick={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
            />
            {open && (
                // Tetap fokus di kolom saat memilih, supaya daftar tidak tertutup sebelum klik terjadi.
                <div className="picker-results" role="listbox" onMouseDown={(event) => event.preventDefault()}>
                    {results.length > 0 && (
                        <div className="picker-head">
                            {searching
                                ? `${total.toLocaleString('id-ID')} siswa cocok`
                                : total > results.length
                                  ? `${results.length} dari ${total.toLocaleString('id-ID')} siswa · ketik nama atau NIS untuk mencari`
                                  : `${total} siswa`}
                        </div>
                    )}
                    {loading && results.length === 0 && <div className="picker-empty">Memuat…</div>}
                    {!loading && results.length === 0 && hidden === 0 && <div className="picker-empty">{searching ? 'Siswa tidak ditemukan.' : 'Belum ada siswa.'}</div>}
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
