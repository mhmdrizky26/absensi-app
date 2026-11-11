import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ImageUp, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Field from '@/Components/Field';
import StudentCard, { CustomCard } from '@/Components/StudentCard';
import AppLayout from '@/Layouts/AppLayout';

const LONG_SIDE = 85.6;
const SHORT_SIDE = 54;

const MODES = [
    { value: 'landscape', label: 'Bawaan landscape', hint: 'Teks di kiri, QR di kanan. 10 kartu per A4.' },
    { value: 'portrait', label: 'Bawaan portrait', hint: 'QR besar di tengah. 9 kartu per A4.' },
    { value: 'custom', label: 'Desain sekolah', hint: 'Gambar latar dari sekolah, QR dan nama diletakkan di atasnya.' },
];

const ELEMENTS = [
    { key: 'qr', label: 'QR' },
    { key: 'name', label: 'Nama' },
    { key: 'nis', label: 'NIS' },
];

const sizeOf = (orientation) => ({
    width: orientation === 'portrait' ? SHORT_SIDE : LONG_SIDE,
    height: orientation === 'portrait' ? LONG_SIDE : SHORT_SIDE,
});

const roundHalf = (value) => Math.round(value * 2) / 2;
const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

/**
 * Tinggi kira-kira kotak teks (mm), untuk bingkai geser dan batas kartu.
 */
const textHeight = (box, lines) => box.size * 1.1 * lines;

function NumberField({ label, value, onChange, step = 0.5, min, max, suffix = 'mm' }) {
    return (
        <label className="design-number">
            <span>{label}</span>
            <input type="number" className="input" step={step} min={min} max={max} value={value} onChange={(event) => onChange(Number(event.target.value))} />
            <span className="text-muted">{suffix}</span>
        </label>
    );
}

export default function CardDesign({ design, defaults, minQrSize, sample }) {
    const { schoolName } = usePage().props;
    const form = useForm({ mode: design.mode, orientation: design.orientation, qr: design.qr, name: design.name, nis: design.nis });
    const backgroundForm = useForm({ background: null });
    const [selected, setSelected] = useState('qr');
    const [removing, setRemoving] = useState(false);
    const cardRef = useRef(null);

    const { width, height } = sizeOf(form.data.orientation);
    const preview = { ...design, ...form.data, width, height };
    const scale = form.data.orientation === 'portrait' ? 2.2 : 2.4;
    const hasBackground = Boolean(design.backgroundUrl);

    function boundsOf(key, data = form.data) {
        if (key === 'qr') {
            return { width: data.qr.size, height: data.qr.size };
        }

        return { width: data[key].width, height: textHeight(data[key], key === 'name' ? 2 : 1) };
    }

    function updateBox(key, changes) {
        form.setData((data) => ({ ...data, [key]: { ...data[key], ...changes } }));
    }

    function changeOrientation(orientation) {
        const start = defaults[orientation];
        form.setData((data) => ({ ...data, orientation, qr: start.qr, name: { ...start.name, color: data.name.color }, nis: { ...start.nis, color: data.nis.color } }));
    }

    function startDrag(key, event) {
        event.preventDefault();
        event.stopPropagation();
        setSelected(key);

        const rect = cardRef.current.getBoundingClientRect();
        const mmPerPx = width / rect.width;
        const start = { pointerX: event.clientX, pointerY: event.clientY, x: form.data[key].x, y: form.data[key].y };

        function move(moveEvent) {
            form.setData((data) => {
                const bounds = boundsOf(key, data);

                return {
                    ...data,
                    [key]: {
                        ...data[key],
                        x: clamp(roundHalf(start.x + (moveEvent.clientX - start.pointerX) * mmPerPx), 0, width - bounds.width),
                        y: clamp(roundHalf(start.y + (moveEvent.clientY - start.pointerY) * mmPerPx), 0, height - bounds.height),
                    },
                };
            });
        }

        function stop() {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
        }

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
    }

    function uploadBackground(event) {
        const file = event.target.files[0];

        if (!file) {
            return;
        }

        backgroundForm.setData('background', file);
        router.post(
            '/kartu/desain/latar',
            { background: file },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => backgroundForm.setError(errors),
                onSuccess: () => backgroundForm.clearErrors(),
            },
        );
        event.target.value = '';
    }

    function submit(event) {
        event.preventDefault();
        form.put('/kartu/desain', { preserveScroll: true });
    }

    const selectedBox = form.data[selected];
    const maxQr = Math.min(width, height);

    return (
        <>
            <Head title="Desain kartu" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">
                        <Link href="/kartu" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                            <ArrowLeft size={14} aria-hidden="true" /> Kartu QR
                        </Link>
                    </h6>
                    <h1>Desain kartu</h1>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Desain hanya mengubah tampilan cetak. Isi QR tetap sama, jadi kartu yang sudah dicetak dengan desain lama tetap bisa dipakai absen. Ukuran kartu selalu seukuran KTP (85,6 × 54 mm).
            </p>

            <form onSubmit={submit} noValidate>
                <div className="design-modes" role="radiogroup" aria-label="Desain kartu">
                    {MODES.map((mode) => (
                        <label key={mode.value} className={`design-mode ${form.data.mode === mode.value ? 'is-active' : ''}`}>
                            <input type="radio" name="mode" checked={form.data.mode === mode.value} onChange={() => form.setData('mode', mode.value)} />
                            <span className="design-mode-preview">
                                {mode.value === 'custom' ? (
                                    hasBackground ? (
                                        <CustomCard card={sample} design={{ ...preview, mode: 'custom' }} />
                                    ) : (
                                        <span className="design-mode-empty">Belum ada gambar latar</span>
                                    )
                                ) : (
                                    <StudentCard card={sample} schoolName={schoolName} design={{ mode: mode.value }} />
                                )}
                            </span>
                            <b>{mode.label}</b>
                            <span className="text-muted">{mode.hint}</span>
                        </label>
                    ))}
                </div>

                {form.data.mode === 'custom' && (
                    <section className="design-editor">
                        <div className="design-editor-side">
                            <h4 style={{ margin: 0 }}>Desain sekolah</h4>

                            <Field label="Arah kartu">
                                <div className="seg" role="radiogroup" aria-label="Arah kartu">
                                    {[
                                        ['landscape', 'Landscape'],
                                        ['portrait', 'Portrait'],
                                    ].map(([value, label]) => (
                                        <label key={value} className="seg-opt">
                                            <input type="radio" name="orientation" checked={form.data.orientation === value} onChange={() => changeOrientation(value)} />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </Field>

                            <Field
                                label="Gambar latar"
                                error={backgroundForm.errors.background}
                                hint={`PNG atau JPG, maks. 5 MB. Ukuran ideal ${form.data.orientation === 'portrait' ? '638 × 1011' : '1011 × 638'} piksel (300 dpi). Gambar ditarik pas seukuran kartu. Sisakan area putih polos untuk QR.`}
                            >
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    <label className="btn btn-secondary" style={{ cursor: 'pointer' }}>
                                        <ImageUp size={16} aria-hidden="true" /> {hasBackground ? 'Ganti gambar' : 'Unggah gambar'}
                                        <input type="file" accept="image/png,image/jpeg" hidden onChange={uploadBackground} />
                                    </label>
                                    {hasBackground && (
                                        <button type="button" className="btn btn-ghost" onClick={() => setRemoving(true)}>
                                            <Trash2 size={16} aria-hidden="true" /> Hapus
                                        </button>
                                    )}
                                </div>
                            </Field>

                            <div className="seg" role="radiogroup" aria-label="Elemen yang diatur">
                                {ELEMENTS.map((element) => (
                                    <label key={element.key} className="seg-opt">
                                        <input type="radio" name="element" checked={selected === element.key} onChange={() => setSelected(element.key)} />
                                        {element.label}
                                    </label>
                                ))}
                            </div>

                            <div className="design-fields">
                                <NumberField label="Kiri" value={selectedBox.x} min={0} onChange={(x) => updateBox(selected, { x })} />
                                <NumberField label="Atas" value={selectedBox.y} min={0} onChange={(y) => updateBox(selected, { y })} />
                                {selected === 'qr' ? (
                                    <NumberField label="Ukuran" value={selectedBox.size} min={minQrSize} max={maxQr} onChange={(size) => updateBox('qr', { size })} />
                                ) : (
                                    <>
                                        <NumberField label="Lebar" value={selectedBox.width} min={10} max={width} onChange={(boxWidth) => updateBox(selected, { width: boxWidth })} />
                                        <NumberField label="Huruf" value={selectedBox.size} step={0.1} min={1.5} max={10} onChange={(size) => updateBox(selected, { size })} />
                                        <label className="design-number">
                                            <span>Warna</span>
                                            <input type="color" className="input design-color" value={selectedBox.color} onChange={(event) => updateBox(selected, { color: event.target.value })} />
                                        </label>
                                        <div className="seg" role="radiogroup" aria-label="Perataan teks">
                                            {[
                                                ['left', 'Kiri'],
                                                ['center', 'Tengah'],
                                                ['right', 'Kanan'],
                                            ].map(([value, label]) => (
                                                <label key={value} className="seg-opt">
                                                    <input type="radio" name="align" checked={selectedBox.align === value} onChange={() => updateBox(selected, { align: value })} />
                                                    {label}
                                                </label>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>

                            {form.data.qr.size < 30 && <p className="alert alert-warning" style={{ margin: 0 }}>QR di bawah 30 mm bisa lambat terbaca kamera tab gerbang. Sebaiknya 35–45 mm.</p>}
                            {Object.entries(form.errors).length > 0 && (
                                <ul className="error-list">
                                    {Object.values(form.errors).map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-muted" style={{ fontSize: 13, margin: 0 }}>
                                Geser QR, nama, dan NIS langsung di kartu, atau atur angkanya di sini. Nama memakai contoh siswa {sample.name}; nama yang lebih panjang dibungkus ke baris berikutnya.
                            </p>
                        </div>

                        <div className="design-canvas">
                            <div style={{ width: `${width * scale}mm`, height: `${height * scale}mm` }}>
                                <div ref={cardRef} style={{ transform: `scale(${scale})`, transformOrigin: 'top left', width: `${width}mm`, height: `${height}mm` }}>
                                    <CustomCard card={sample} design={preview}>
                                        {!hasBackground && <span className="design-no-background">Unggah gambar latar desain sekolah</span>}
                                        {ELEMENTS.map((element) => {
                                            const bounds = boundsOf(element.key);

                                            return (
                                                <button
                                                    key={element.key}
                                                    type="button"
                                                    className={`design-handle ${selected === element.key ? 'is-selected' : ''}`}
                                                    style={{ left: `${form.data[element.key].x}mm`, top: `${form.data[element.key].y}mm`, width: `${bounds.width}mm`, height: `${bounds.height}mm` }}
                                                    aria-label={`Geser ${element.label}`}
                                                    onPointerDown={(event) => startDrag(element.key, event)}
                                                >
                                                    <span>{element.label}</span>
                                                </button>
                                            );
                                        })}
                                    </CustomCard>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                <div style={{ marginTop: 24 }}>
                    <button type="submit" className="btn btn-primary" disabled={form.processing || (form.data.mode === 'custom' && !hasBackground)}>
                        Simpan desain
                    </button>
                </div>
            </form>

            <ConfirmDialog
                open={removing}
                title="Hapus gambar latar?"
                confirmLabel="Hapus"
                onConfirm={() => router.delete('/kartu/desain/latar', { preserveScroll: true, onFinish: () => setRemoving(false) })}
                onClose={() => setRemoving(false)}
            >
                Kartu kembali memakai desain bawaan sampai gambar latar baru diunggah.
            </ConfirmDialog>
        </>
    );
}

CardDesign.layout = (page) => <AppLayout>{page}</AppLayout>;
