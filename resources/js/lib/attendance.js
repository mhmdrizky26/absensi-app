/**
 * Terlambat (T) dan Dispensasi (D) tetap dihitung hadir; keduanya hanya tanda tambahan.
 */
export const PRESENT_STATUSES = ['H', 'T', 'D'];

export const isPresent = (status) => PRESENT_STATUSES.includes(status);

/** Jumlah semua yang hadir: tepat waktu + terlambat + dispensasi. */
export const presentCount = (counts) => PRESENT_STATUSES.reduce((sum, status) => sum + (counts?.[status] ?? 0), 0);
