import { qrMatrix } from '@/lib/qr';

const PX_PER_MM = 12; // ± 305 dpi
const CARD_W = 85.6;
const CARD_H = 54;
const QR_COLUMN = 38;

const mm = (value) => Math.round(value * PX_PER_MM);

/**
 * Pecah teks menjadi baris yang muat di lebar tertentu.
 */
function wrapText(context, text, maxWidth) {
    const lines = [];
    let line = '';

    for (const word of text.split(/\s+/)) {
        const candidate = line ? `${line} ${word}` : word;

        if (context.measureText(candidate).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }

    if (line) {
        lines.push(line);
    }

    return lines;
}

/**
 * Gambar kartu yang sama dengan <StudentCard> ke PNG lalu unduh.
 */
export async function downloadCardPng(card, schoolName) {
    await document.fonts?.ready;

    const canvas = document.createElement('canvas');
    canvas.width = mm(CARD_W);
    canvas.height = mm(CARD_H);
    const context = canvas.getContext('2d');
    const font = getComputedStyle(document.body).fontFamily;
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim() || '#2360e8';

    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);

    // Bingkai dan garis pemisah kolom QR.
    context.strokeStyle = '#000';
    context.lineWidth = mm(0.6);
    context.strokeRect(mm(0.3), mm(0.3), canvas.width - mm(0.6), canvas.height - mm(0.6));
    const qrLeft = CARD_W - QR_COLUMN;
    context.beginPath();
    context.moveTo(mm(qrLeft), 0);
    context.lineTo(mm(qrLeft), canvas.height);
    context.stroke();

    // QR code di kolom kanan.
    const { size, isDark } = qrMatrix(card.payload);
    const qrSide = QR_COLUMN - 4;
    const dot = mm(qrSide) / (size + 4);
    const originX = mm(qrLeft + 2) + dot * 2;
    const originY = (canvas.height - mm(qrSide)) / 2 + dot * 2;
    context.fillStyle = '#000';

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            if (isDark(x, y)) {
                context.fillRect(Math.floor(originX + x * dot), Math.floor(originY + y * dot), Math.ceil(dot), Math.ceil(dot));
            }
        }
    }

    // Teks di kolom kiri.
    const left = mm(4);
    const textWidth = mm(qrLeft - 8);
    context.textBaseline = 'top';

    context.fillStyle = accent;
    context.font = `600 ${mm(2.4)}px ${font}`;
    context.fillText(schoolName.toUpperCase(), left, mm(4), textWidth);
    context.fillStyle = '#555';
    context.font = `400 ${mm(2.2)}px ${font}`;
    context.fillText('Kartu absensi siswa', left, mm(7.4), textWidth);

    const nameSize = card.name.length > 34 ? 3.4 : card.name.length > 22 ? 4 : 4.8;
    context.fillStyle = '#000';
    context.font = `800 ${mm(nameSize)}px ${font}`;
    const lines = wrapText(context, card.name, textWidth).slice(0, 3);
    const lineHeight = mm(nameSize * 1.08);
    const nameTop = mm(27) - (lines.length * lineHeight) / 2;
    lines.forEach((line, index) => context.fillText(line, left, nameTop + index * lineHeight));

    context.font = `400 ${mm(2.8)}px ${font}`;
    context.fillText(`NIS ${card.nis}`, left, nameTop + lines.length * lineHeight + mm(1.5), textWidth);

    context.fillStyle = '#555';
    context.font = `400 ${mm(2)}px ${font}`;
    context.fillText(`Tunjukkan ke guru saat absen · v${card.version}`, left, mm(CARD_H - 6.5), textWidth);

    const link = document.createElement('a');
    link.download = `kartu-${card.nis}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}
