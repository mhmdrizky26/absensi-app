import { useMemo } from 'react';
import { qrPath } from '@/lib/qr';

/**
 * Kode QR sebagai SVG tajam di ukuran berapa pun, termasuk saat dicetak.
 * `quietZone` adalah margin putih dalam satuan titik QR.
 */
export default function QrCode({ value, quietZone = 2, className, style, title }) {
    const { size, path } = useMemo(() => qrPath(value), [value]);
    const box = size + quietZone * 2;

    return (
        <svg className={className} style={style} viewBox={`${-quietZone} ${-quietZone} ${box} ${box}`} shapeRendering="crispEdges" role="img" aria-label={title ?? 'Kode QR'}>
            <rect x={-quietZone} y={-quietZone} width={box} height={box} fill="#fff" />
            <path d={path} fill="#000" />
        </svg>
    );
}
