import jsQR from 'jsqr';

/**
 * Kamera belakang + pembaca QR. Memakai BarcodeDetector bawaan browser bila
 * ada (Chrome Android, sangat cepat), selain itu jsQR di atas canvas.
 */

const DECODE_INTERVAL_MS = 90;
const JSQR_SIZE = 640;

export class CameraError extends Error {}

function describeCameraError(error) {
    if (!window.isSecureContext) {
        return 'Kamera hanya bisa dipakai lewat alamat https://. Minta admin membuka aplikasi lewat alamat https.';
    }

    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Izin kamera ditolak. Buka pengaturan situs di browser, izinkan Kamera, lalu muat ulang halaman.';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'Kamera tidak ditemukan di perangkat ini.';
        case 'NotReadableError':
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        default:
            return 'Kamera tidak bisa dibuka. Muat ulang halaman lalu coba lagi.';
    }
}

async function createDecoder() {
    if ('BarcodeDetector' in window) {
        try {
            const formats = await window.BarcodeDetector.getSupportedFormats();

            if (formats.includes('qr_code')) {
                const detector = new window.BarcodeDetector({ formats: ['qr_code'] });

                return async (video) => (await detector.detect(video))[0]?.rawValue ?? null;
            }
        } catch {
            // Lanjut ke jsQR.
        }
    }

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let fullFrame = false;

    return async (video) => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        if (!width || !height) {
            return null;
        }

        // Bergantian: kotak tengah (tempat bingkai, cepat) lalu seluruh
        // gambar kamera, supaya kartu yang tidak pas di tengah tetap terbaca.
        fullFrame = !fullFrame;
        const side = Math.min(width, height) * 0.85;
        const source = fullFrame ? [0, 0, width, height] : [(width - side) / 2, (height - side) / 2, side, side];
        const scale = JSQR_SIZE / Math.max(source[2], source[3]);
        canvas.width = Math.round(source[2] * scale);
        canvas.height = Math.round(source[3] * scale);
        context.drawImage(video, ...source, 0, 0, canvas.width, canvas.height);
        const image = context.getImageData(0, 0, canvas.width, canvas.height);

        return jsQR(image.data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
    };
}

/**
 * Nyalakan kamera di elemen <video> dan panggil onRead(teks) setiap QR terbaca.
 * Mengembalikan fungsi untuk mematikan kamera.
 */
export async function startScanner(video, onRead) {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new CameraError(describeCameraError(null));
    }

    let stream;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        });
    } catch (error) {
        throw new CameraError(describeCameraError(error));
    }

    video.srcObject = stream;
    video.setAttribute('playsinline', '');
    video.muted = true;
    await video.play();

    const decode = await createDecoder();
    let stopped = false;
    let busy = false;

    const timer = setInterval(async () => {
        if (stopped || busy || video.readyState < 2) {
            return;
        }

        busy = true;

        try {
            const text = await decode(video);

            if (text && !stopped) {
                onRead(text);
            }
        } catch {
            // Frame gagal dibaca; coba frame berikutnya.
        } finally {
            busy = false;
        }
    }, DECODE_INTERVAL_MS);

    return () => {
        stopped = true;
        clearInterval(timer);
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };
}
