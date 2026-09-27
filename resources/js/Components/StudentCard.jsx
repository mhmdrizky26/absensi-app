import QrCode from '@/Components/QrCode';

/**
 * Kartu absensi siswa ukuran CR80 (85,6 × 54 mm), sama dengan KTP. Kelas
 * dan tahun ajaran sengaja tidak dicetak supaya kartu bisa dipakai sampai lulus.
 */
export default function StudentCard({ card, schoolName }) {
    const nameSize = card.name.length > 34 ? 'is-long' : card.name.length > 22 ? 'is-medium' : '';

    return (
        <div className="id-card">
            <div className="id-card-info">
                <div>
                    <div className="id-card-school">{schoolName}</div>
                    <div className="id-card-kind">Kartu absensi siswa</div>
                </div>
                <div>
                    <div className={`id-card-name ${nameSize}`}>{card.name}</div>
                    <div className="id-card-meta">NIS {card.nis}</div>
                </div>
                <div className="id-card-foot">Tunjukkan ke guru saat absen · v{card.version}</div>
            </div>
            <div className="id-card-qr">
                <QrCode value={card.payload} quietZone={2} title={`QR ${card.name}`} />
            </div>
        </div>
    );
}
