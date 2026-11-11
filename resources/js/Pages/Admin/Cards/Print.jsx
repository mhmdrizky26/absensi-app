import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import StudentCard from '@/Components/StudentCard';

/**
 * Kartu landscape: 10 per lembar A4 (2 kolom × 5 baris). Kartu portrait: 9 per
 * lembar (3 × 3). Tanpa jarak antarkartu supaya sekali potong memisahkan dua kartu.
 */
function isPortrait(design) {
    return design?.mode === 'portrait' || (design?.mode === 'custom' && design.backgroundUrl && design.orientation === 'portrait');
}

/**
 * Tata letak lembar A4 (mm), sama dengan CSS .print-sheet.
 */
const LAYOUTS = {
    landscape: { columns: 2, cardWidth: 85.6, cardHeight: 54, left: 19.4, top: 13.5 },
    portrait: { columns: 3, cardWidth: 54, cardHeight: 85.6, left: 24, top: 20 },
};

const MARK_GAP = 1.5;
const MARK_LENGTH = 6;

/**
 * Garis potong untuk desain sekolah, yang gambarnya penuh sampai tepi kartu:
 * garis putus-putus di batas antarkartu (hitam di atas putih, jadi tetap
 * terlihat di latar gelap maupun terang) dan tanda potong di pinggir kertas.
 */
function CutMarks({ layout, count }) {
    const { columns, cardWidth, cardHeight, left, top } = layout;
    const rows = Math.ceil(count / columns);
    const right = left + columns * cardWidth;
    const bottom = top + rows * cardHeight;
    const lines = [];
    const marks = [];

    for (let column = 0; column <= columns; column++) {
        const x = left + column * cardWidth;
        lines.push([x, top, x, bottom]);
        marks.push([x, top - MARK_GAP - MARK_LENGTH, x, top - MARK_GAP], [x, bottom + MARK_GAP, x, bottom + MARK_GAP + MARK_LENGTH]);
    }

    for (let row = 0; row <= rows; row++) {
        const y = top + row * cardHeight;
        lines.push([left, y, right, y]);
        marks.push([left - MARK_GAP - MARK_LENGTH, y, left - MARK_GAP, y], [right + MARK_GAP, y, right + MARK_GAP + MARK_LENGTH, y]);
    }

    return (
        <svg className="print-cut-marks" viewBox="0 0 210 297" aria-hidden="true">
            {lines.map(([x1, y1, x2, y2]) => (
                <g key={`line-${x1}-${y1}-${x2}-${y2}`}>
                    <line x1={x1} y1={y1} x2={x2} y2={y2} stroke="#fff" strokeWidth="0.45" />
                    <line x1={x1} y1={y1} x2={x2} y2={y2} stroke="#000" strokeWidth="0.2" strokeDasharray="1.2 1" />
                </g>
            ))}
            {marks.map(([x1, y1, x2, y2]) => (
                <line key={`mark-${x1}-${y1}-${x2}-${y2}`} x1={x1} y1={y1} x2={x2} y2={y2} stroke="#000" strokeWidth="0.25" />
            ))}
        </svg>
    );
}

export default function CardsPrint({ title, cards, cardDesign }) {
    const { schoolName } = usePage().props;
    const portrait = isPortrait(cardDesign);
    const isSchoolDesign = cardDesign?.mode === 'custom' && Boolean(cardDesign.backgroundUrl);
    const perSheet = portrait ? 9 : 10;
    const sheets = [];

    for (let index = 0; index < cards.length; index += perSheet) {
        sheets.push(cards.slice(index, index + perSheet));
    }

    return (
        <>
            <Head title={`Cetak kartu · ${title}`} />

            <div className="print-toolbar">
                <div>
                    <h2 style={{ margin: 0 }}>{title}</h2>
                    <p className="text-muted" style={{ margin: 0, fontSize: 14 }}>
                        {cards.length} kartu · {sheets.length} lembar A4. Saat mencetak pilih kertas A4, skala 100% (matikan "Fit to page"), dan aktifkan "Background graphics".
                    </p>
                </div>
                <button type="button" className="btn btn-primary" onClick={() => window.print()} disabled={cards.length === 0}>
                    <Printer size={16} aria-hidden="true" /> Cetak
                </button>
            </div>

            {cards.length === 0 ? (
                <p className="text-muted" style={{ padding: 24 }}>
                    Tidak ada kartu yang bisa dicetak. Kartu yang dicabut dan siswa yang tidak aktif tidak ikut dicetak.
                </p>
            ) : (
                sheets.map((sheet, index) => (
                    <section key={index} className={`print-sheet ${portrait ? 'is-portrait' : ''}`}>
                        {sheet.map((card) => (
                            <StudentCard key={card.id} card={card} schoolName={schoolName} design={cardDesign} />
                        ))}
                        {isSchoolDesign && <CutMarks layout={LAYOUTS[portrait ? 'portrait' : 'landscape']} count={sheet.length} />}
                    </section>
                ))
            )}
        </>
    );
}
