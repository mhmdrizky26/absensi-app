/**
 * Sidik jari teks QR, sama dengan Student::qrHash() di server:
 * 16 karakter pertama SHA-256 dalam heksadesimal.
 */
export async function qrHash(payload) {
    const bytes = new TextEncoder().encode(payload);
    const digest = await crypto.subtle.digest('SHA-256', bytes);

    return Array.from(new Uint8Array(digest))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('')
        .slice(0, 16);
}
