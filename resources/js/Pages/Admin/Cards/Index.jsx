import { Head, Link, router, usePage } from '@inertiajs/react';
import { Ban, Download, Printer, RefreshCw, RotateCcw } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import StudentCard from '@/Components/StudentCard';
import AppLayout from '@/Layouts/AppLayout';
import { downloadCardPng } from '@/lib/cardImage';
import { formatDate } from '@/lib/format';

export default function CardsIndex({ academicYear, classrooms, classroom, students }) {
    const { schoolName } = usePage().props;
    const [selectedId, setSelectedId] = useState(students[0]?.id ?? null);
    const [checked, setChecked] = useState([]);
    const [confirming, setConfirming] = useState(null);
    const panelRef = useRef(null);

    if (!academicYear || !classroom) {
        return (
            <>
                <Head title="Kartu QR" />
                <div className="page-head">
                    <h1>Kartu QR</h1>
                </div>
                <div className="empty">
                    <h4>Belum ada kelas</h4>
                    <p className="text-muted">Kartu dicetak per kelas. Aktifkan tahun ajaran dan buat kelasnya terlebih dahulu.</p>
                    <Link href="/kelas" className="btn btn-primary">
                        Atur kelas
                    </Link>
                </div>
            </>
        );
    }

    const selected = students.find((student) => student.id === selectedId) ?? students[0];
    const printable = students.filter((student) => !student.isRevoked);
    const allChecked = printable.length > 0 && printable.every((student) => checked.includes(student.id));

    function changeClassroom(id) {
        setChecked([]);
        router.get('/kartu', { classroom: id }, { preserveScroll: true, onSuccess: (page) => setSelectedId(page.props.students[0]?.id ?? null) });
    }

    function select(id) {
        setSelectedId(id);

        // Di layar sempit panel kartu ada di bawah daftar, jadi gulir ke sana.
        if (window.matchMedia('(max-width: 960px)').matches) {
            requestAnimationFrame(() => panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    }

    function toggle(id) {
        setChecked((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));
    }

    function runConfirmed() {
        const { action, student } = confirming;
        const url = `/siswa/${student.id}/kartu${action === 'reissue' ? '' : '/cabut'}`;
        const options = { preserveScroll: true, onFinish: () => setConfirming(null) };

        if (action === 'restore') {
            router.delete(url, options);
        } else {
            router.post(url, {}, options);
        }
    }

    const confirmText = {
        reissue: {
            title: `Buat kartu baru untuk ${confirming?.student.name}?`,
            body: `Kartu yang sekarang (v${confirming?.student.version}) langsung tidak bisa dipakai absen. Pakai ini kalau kartu hilang, rusak, atau dipinjamkan ke teman. Setelah itu cetak kartu barunya.`,
            label: 'Buat kartu baru',
        },
        revoke: {
            title: `Cabut kartu ${confirming?.student.name}?`,
            body: 'Kartu ini tidak bisa dipakai absen sampai diaktifkan kembali. Siswa tetap bisa diabsen manual oleh guru.',
            label: 'Cabut kartu',
        },
        restore: {
            title: `Aktifkan kembali kartu ${confirming?.student.name}?`,
            body: 'Kartu yang sama bisa dipakai absen lagi.',
            label: 'Aktifkan',
        },
    }[confirming?.action ?? 'reissue'];

    return (
        <>
            <Head title="Kartu QR" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Tahun ajaran {academicYear.name}</h6>
                    <h1>Kartu QR · {classroom.name}</h1>
                </div>
                <div className="page-actions">
                    {checked.length > 0 && (
                        <a href={`/kartu/cetak?students=${checked.join(',')}`} target="_blank" rel="noreferrer" className="btn btn-secondary">
                            <Printer size={16} aria-hidden="true" /> Cetak terpilih ({checked.length})
                        </a>
                    )}
                    <a href={`/kartu/cetak?classroom=${classroom.id}`} target="_blank" rel="noreferrer" className="btn btn-primary">
                        <Printer size={16} aria-hidden="true" /> Cetak satu kelas ({printable.length})
                    </a>
                </div>
            </div>

            <div className="toolbar">
                <div className="field">
                    <label htmlFor="classroom">Kelas</label>
                    <select id="classroom" className="input" value={classroom.id} onChange={(event) => changeClassroom(event.target.value)}>
                        {classrooms.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                </div>
                <p className="text-muted" style={{ fontSize: 13, margin: 0, flex: '1 1 320px' }}>
                    Kartu hanya berisi nama dan NIS, tanpa kelas, jadi tetap bisa dipakai setelah naik kelas. Kartu yang dicabut tidak ikut dicetak.
                </p>
            </div>

            <div className="cards-layout">
                <div style={{ minWidth: 0 }}>
                    {students.length === 0 ? (
                        <div className="empty">
                            <h4>Belum ada siswa di kelas ini</h4>
                            <p className="text-muted">
                                Tambahkan siswa di menu <Link href="/siswa">Siswa</Link>.
                            </p>
                        </div>
                    ) : (
                        <div className="table-wrap">
                            <table className="table" style={{ minWidth: 520 }}>
                                <thead>
                                    <tr>
                                        <th style={{ width: 36 }}>
                                            <input
                                                type="checkbox"
                                                aria-label="Pilih semua"
                                                checked={allChecked}
                                                onChange={() => setChecked(allChecked ? [] : printable.map((student) => student.id))}
                                            />
                                        </th>
                                        <th>Siswa</th>
                                        <th style={{ width: 70 }}>Versi</th>
                                        <th style={{ width: 120 }}>Diterbitkan</th>
                                        <th style={{ width: 100 }}>Kartu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {students.map((student) => (
                                        <tr key={student.id} className={student.id === selected?.id ? 'is-selected' : undefined} onClick={() => select(student.id)} style={{ cursor: 'pointer' }}>
                                            <td onClick={(event) => event.stopPropagation()}>
                                                <input type="checkbox" aria-label={`Pilih ${student.name}`} disabled={student.isRevoked} checked={checked.includes(student.id)} onChange={() => toggle(student.id)} />
                                            </td>
                                            <td>
                                                <span style={{ fontWeight: 600 }}>{student.name}</span>
                                                <span className="cell-sub">NIS {student.nis}</span>
                                            </td>
                                            <td>v{student.version}</td>
                                            <td>{formatDate(student.issuedAt)}</td>
                                            <td>
                                                <span className={`tag ${student.isRevoked ? 'tag-accent' : 'tag-neutral'}`}>{student.isRevoked ? 'Dicabut' : 'Aktif'}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {selected && (
                    <aside ref={panelRef} className="card-panel">
                        <h6 className="text-muted" style={{ margin: '0 0 4px' }}>
                            Siswa terpilih
                        </h6>
                        <h3 style={{ margin: 0 }}>{selected.name}</h3>
                        <div className="text-muted" style={{ fontSize: 13, marginBottom: 16 }}>
                            NIS {selected.nis}
                            {selected.nisn ? ` · NISN ${selected.nisn}` : ''} · Kelas {classroom.name}
                        </div>

                        <div className="card-preview">
                            <StudentCard card={selected} schoolName={schoolName} />
                            {selected.isRevoked && <div className="card-revoked">Dicabut — kartu ini tidak bisa dipakai absen</div>}
                        </div>

                        <div className="card-facts">
                            <div>
                                <div className="text-muted">Versi kartu</div>v{selected.version}
                            </div>
                            <div>
                                <div className="text-muted">Diterbitkan</div>
                                {formatDate(selected.issuedAt)}
                            </div>
                        </div>

                        <div className="card-actions">
                            <a href={`/kartu/cetak?students=${selected.id}`} target="_blank" rel="noreferrer" className={`btn btn-secondary btn-block ${selected.isRevoked ? 'is-disabled' : ''}`} aria-disabled={selected.isRevoked}>
                                Cetak kartu <Printer size={16} aria-hidden="true" />
                            </a>
                            <button type="button" className="btn btn-secondary btn-block" disabled={selected.isRevoked} onClick={() => downloadCardPng(selected, schoolName)}>
                                Unduh PNG <Download size={16} aria-hidden="true" />
                            </button>
                            <button type="button" className="btn btn-secondary btn-block" onClick={() => setConfirming({ action: 'reissue', student: selected })}>
                                Buat kartu baru <RefreshCw size={16} aria-hidden="true" />
                            </button>
                            {selected.isRevoked ? (
                                <button type="button" className="btn btn-ghost btn-block" onClick={() => setConfirming({ action: 'restore', student: selected })}>
                                    Aktifkan kembali kartu <RotateCcw size={16} aria-hidden="true" />
                                </button>
                            ) : (
                                <button type="button" className="btn btn-ghost btn-block" onClick={() => setConfirming({ action: 'revoke', student: selected })}>
                                    Cabut kartu <Ban size={16} aria-hidden="true" />
                                </button>
                            )}
                        </div>
                    </aside>
                )}
            </div>

            <ConfirmDialog open={confirming !== null} title={confirmText.title} confirmLabel={confirmText.label} onConfirm={runConfirmed} onClose={() => setConfirming(null)}>
                {confirmText.body}
            </ConfirmDialog>
        </>
    );
}

CardsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
