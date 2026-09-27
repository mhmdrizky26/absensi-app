import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import StudentCard from '@/Components/StudentCard';

/**
 * 10 kartu per lembar A4 (2 kolom × 5 baris), tanpa jarak antarkartu supaya
 * sekali potong memisahkan dua kartu.
 */
const CARDS_PER_SHEET = 10;

export default function CardsPrint({ title, cards }) {
    const { schoolName } = usePage().props;
    const sheets = [];

    for (let index = 0; index < cards.length; index += CARDS_PER_SHEET) {
        sheets.push(cards.slice(index, index + CARDS_PER_SHEET));
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
                    <section key={index} className="print-sheet">
                        {sheet.map((card) => (
                            <StudentCard key={card.id} card={card} schoolName={schoolName} />
                        ))}
                    </section>
                ))
            )}
        </>
    );
}
