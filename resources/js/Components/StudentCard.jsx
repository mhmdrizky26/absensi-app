import QrCode from '@/Components/QrCode';

/**
 * Ukuran huruf nama yang mengecil untuk nama panjang (desain bawaan).
 */
function nameClass(name) {
    return name.length > 34 ? 'is-long' : name.length > 22 ? 'is-medium' : '';
}

/**
 * Desain bawaan landscape: teks di kiri, QR di kanan.
 */
function LandscapeCard({ card, schoolName }) {
    return (
        <div className="id-card">
            <div className="id-card-info">
                <div>
                    <div className="id-card-school">{schoolName}</div>
                    <div className="id-card-kind">Kartu absensi siswa</div>
                </div>
                <div>
                    <div className={`id-card-name ${nameClass(card.name)}`}>{card.name}</div>
                    <div className="id-card-meta">NIS {card.nis}</div>
                </div>
                <div className="id-card-foot">Tempelkan QR ke kamera tab gerbang · v{card.version}</div>
            </div>
            <div className="id-card-qr">
                <QrCode value={card.payload} quietZone={2} title={`QR ${card.name}`} />
            </div>
        </div>
    );
}

/**
 * Desain bawaan portrait: kepala biru, QR besar di tengah, nama di bawahnya.
 */
function PortraitCard({ card, schoolName }) {
    return (
        <div className="id-card-portrait">
            <div className="id-card-portrait-head">
                <div className="id-card-school">{schoolName}</div>
                <div className="id-card-kind">Kartu absensi siswa</div>
            </div>
            <div className="id-card-portrait-qr">
                <QrCode value={card.payload} quietZone={2} title={`QR ${card.name}`} />
            </div>
            <div className="id-card-portrait-body">
                <div className={`id-card-name ${card.name.length > 22 ? 'is-long' : ''}`}>{card.name}</div>
                <div className="id-card-meta">NIS {card.nis}</div>
                <div className="id-card-foot">Tempelkan QR ke kamera tab gerbang · v{card.version}</div>
            </div>
        </div>
    );
}

/**
 * Desain sekolah: gambar latar dari sekolah, lalu QR, nama, dan NIS di posisi
 * yang diatur admin (dalam mm dari pojok kiri atas).
 */
export function CustomCard({ card, design, children }) {
    const box = (field, weight) => ({
        left: `${design[field].x}mm`,
        top: `${design[field].y}mm`,
        width: `${design[field].width}mm`,
        fontSize: `${design[field].size}mm`,
        color: design[field].color,
        textAlign: design[field].align,
        fontWeight: weight,
    });

    return (
        <div className="id-card-custom" style={{ width: `${design.width}mm`, height: `${design.height}mm` }}>
            {design.backgroundUrl && <img className="id-card-custom-bg" src={design.backgroundUrl} alt="" draggable={false} />}
            <div className="id-card-custom-qr" style={{ left: `${design.qr.x}mm`, top: `${design.qr.y}mm`, width: `${design.qr.size}mm`, height: `${design.qr.size}mm` }}>
                <QrCode value={card.payload} quietZone={2} title={`QR ${card.name}`} />
            </div>
            <div className="id-card-custom-text is-name" style={box('name', 800)}>
                {card.name}
            </div>
            <div className="id-card-custom-text" style={box('nis', 600)}>
                NIS {card.nis}
            </div>
            {children}
        </div>
    );
}

/**
 * Kartu absensi siswa ukuran CR80 (85,6 × 54 mm), sama dengan KTP, dengan
 * desain yang dipilih admin di menu Kartu QR → Desain kartu. Kelas dan tahun
 * ajaran sengaja tidak dicetak supaya kartu bisa dipakai sampai lulus.
 */
export default function StudentCard({ card, schoolName, design }) {
    if (design?.mode === 'custom' && design.backgroundUrl) {
        return <CustomCard card={card} design={design} />;
    }

    if (design?.mode === 'portrait') {
        return <PortraitCard card={card} schoolName={schoolName} />;
    }

    return <LandscapeCard card={card} schoolName={schoolName} />;
}
