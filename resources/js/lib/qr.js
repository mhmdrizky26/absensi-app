import QRCode from 'qrcode';

/**
 * Matriks titik QR untuk teks kartu. Koreksi galat level M: tetap terbaca
 * walau kartu sedikit tergores, dan payload kartu tetap muat di QR versi 1.
 *
 * @returns {{ size: number, isDark: (x: number, y: number) => boolean }}
 */
export function qrMatrix(text) {
    const { modules } = QRCode.create(text, { errorCorrectionLevel: 'M' });

    return {
        size: modules.size,
        isDark: (x, y) => Boolean(modules.get(y, x)),
    };
}

/**
 * Path SVG satu titik per kotak 1×1, untuk viewBox 0 0 size size.
 */
export function qrPath(text) {
    const { size, isDark } = qrMatrix(text);
    let path = '';

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            if (isDark(x, y)) {
                path += `M${x} ${y}h1v1h-1z`;
            }
        }
    }

    return { size, path };
}
