import { qrMatrix } from '@/lib/qr';

const PX_PER_MM = 12; // ± 305 dpi
const LONG_SIDE = 85.6;
const SHORT_SIDE = 54;
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
 * QR dengan pinggiran putih 2 titik, persegi di (left, top) selebar side (mm).
 */
function drawQr(context, payload, left, top, side) {
    const { size, isDark } = qrMatrix(payload);
    const dot = mm(side) / (size + 4);
    const originX = mm(left) + dot * 2;
    const originY = mm(top) + dot * 2;

    context.fillStyle = '#fff';
    context.fillRect(mm(left), mm(top), mm(side), mm(side));
    context.fillStyle = '#000';

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            if (isDark(x, y)) {
                context.fillRect(Math.floor(originX + x * dot), Math.floor(originY + y * dot), Math.ceil(dot), Math.ceil(dot));
            }
        }
    }
}

/**
 * Teks rata kiri/tengah/kanan di dalam kotak selebar width (mm), dibungkus
 * sampai maxLines baris. Mengembalikan posisi bawah teks (px).
 */
function drawText(context, text, { x, y, width, size, color, align, weight, font, maxLines = 3, lineHeight = 1.1 }) {
    context.fillStyle = color;
    context.font = `${weight} ${mm(size)}px ${font}`;
    context.textBaseline = 'top';
    context.textAlign = align;
    const lines = wrapText(context, text, mm(width)).slice(0, maxLines);
    const anchorX = align === 'center' ? mm(x + width / 2) : align === 'right' ? mm(x + width) : mm(x);

    lines.forEach((line, index) => context.fillText(line, anchorX, mm(y) + index * mm(size * lineHeight), mm(width)));
    context.textAlign = 'left';

    return mm(y) + lines.length * mm(size * lineHeight);
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = url;
    });
}

function drawLandscape(context, card, schoolName, font, accent) {
    const width = context.canvas.width;
    const height = context.canvas.height;

    context.strokeStyle = '#000';
    context.lineWidth = mm(0.6);
    context.strokeRect(mm(0.3), mm(0.3), width - mm(0.6), height - mm(0.6));
    const qrLeft = LONG_SIDE - QR_COLUMN;
    context.beginPath();
    context.moveTo(mm(qrLeft), 0);
    context.lineTo(mm(qrLeft), height);
    context.stroke();

    drawQr(context, card.payload, qrLeft + 2, (SHORT_SIDE - (QR_COLUMN - 4)) / 2, QR_COLUMN - 4);

    const textWidth = qrLeft - 8;
    drawText(context, schoolName.toUpperCase(), { x: 4, y: 4, width: textWidth, size: 2.4, color: accent, align: 'left', weight: 600, font, maxLines: 1 });
    drawText(context, 'Kartu absensi siswa', { x: 4, y: 7.4, width: textWidth, size: 2.2, color: '#555', align: 'left', weight: 400, font, maxLines: 1 });

    const nameSize = card.name.length > 34 ? 3.4 : card.name.length > 22 ? 4 : 4.8;
    context.font = `800 ${mm(nameSize)}px ${font}`;
    const lineCount = Math.min(wrapText(context, card.name, mm(textWidth)).length, 3);
    const nameTop = 27 - (lineCount * nameSize * 1.08) / 2;
    const nameBottom = drawText(context, card.name, { x: 4, y: nameTop, width: textWidth, size: nameSize, color: '#000', align: 'left', weight: 800, font, lineHeight: 1.08 });
    drawText(context, `NIS ${card.nis}`, { x: 4, y: nameBottom / PX_PER_MM + 1.5, width: textWidth, size: 2.8, color: '#000', align: 'left', weight: 400, font, maxLines: 1 });
    drawText(context, `Tempelkan QR ke kamera tab gerbang · v${card.version}`, { x: 4, y: SHORT_SIDE - 6.5, width: textWidth, size: 2, color: '#555', align: 'left', weight: 400, font, maxLines: 1 });
}

function drawPortrait(context, card, schoolName, font, accent) {
    const width = context.canvas.width;
    const height = context.canvas.height;

    context.fillStyle = accent;
    context.fillRect(0, 0, width, mm(11));
    context.strokeStyle = '#000';
    context.lineWidth = mm(0.6);
    context.strokeRect(mm(0.3), mm(0.3), width - mm(0.6), height - mm(0.6));
    context.beginPath();
    context.moveTo(0, mm(11));
    context.lineTo(width, mm(11));
    context.stroke();

    drawText(context, schoolName.toUpperCase(), { x: 3.4, y: 3, width: SHORT_SIDE - 6.8, size: 2.4, color: '#fff', align: 'left', weight: 600, font, maxLines: 1 });
    drawText(context, 'Kartu absensi siswa', { x: 3.4, y: 6.4, width: SHORT_SIDE - 6.8, size: 2.2, color: 'rgba(255,255,255,.85)', align: 'left', weight: 400, font, maxLines: 1 });

    drawQr(context, card.payload, 5, 13.6, 44);

    const nameSize = card.name.length > 22 ? 3.4 : 4.2;
    const nameBottom = drawText(context, card.name, { x: 3.4, y: 60, width: SHORT_SIDE - 6.8, size: nameSize, color: '#000', align: 'center', weight: 800, font, maxLines: 2, lineHeight: 1.08 });
    drawText(context, `NIS ${card.nis}`, { x: 3.4, y: nameBottom / PX_PER_MM + 1.2, width: SHORT_SIDE - 6.8, size: 2.8, color: '#000', align: 'center', weight: 400, font, maxLines: 1 });
    drawText(context, `Tempelkan QR ke kamera tab gerbang · v${card.version}`, { x: 3.4, y: LONG_SIDE - 6, width: SHORT_SIDE - 6.8, size: 2, color: '#555', align: 'center', weight: 400, font, maxLines: 1 });
}

async function drawCustom(context, card, design, font) {
    const background = await loadImage(design.backgroundUrl);
    context.drawImage(background, 0, 0, context.canvas.width, context.canvas.height);
    drawQr(context, card.payload, design.qr.x, design.qr.y, design.qr.size);
    drawText(context, card.name, { ...design.name, weight: 800, font });
    drawText(context, `NIS ${card.nis}`, { ...design.nis, weight: 600, font, maxLines: 1 });
}

/**
 * Gambar kartu yang sama dengan <StudentCard> (desain yang sedang dipakai) ke
 * PNG lalu unduh.
 */
export async function downloadCardPng(card, schoolName, design) {
    await document.fonts?.ready;

    const isCustom = design?.mode === 'custom' && design.backgroundUrl;
    const portrait = design?.mode === 'portrait' || (isCustom && design.orientation === 'portrait');
    const canvas = document.createElement('canvas');
    canvas.width = mm(portrait ? SHORT_SIDE : LONG_SIDE);
    canvas.height = mm(portrait ? LONG_SIDE : SHORT_SIDE);
    const context = canvas.getContext('2d');
    const font = getComputedStyle(document.body).fontFamily;
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim() || '#2360e8';

    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);

    if (isCustom) {
        await drawCustom(context, card, design, font);
    } else if (portrait) {
        drawPortrait(context, card, schoolName, font, accent);
    } else {
        drawLandscape(context, card, schoolName, font, accent);
    }

    const link = document.createElement('a');
    link.download = `kartu-${card.nis}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}
