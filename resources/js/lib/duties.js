/**
 * Peran akun hanya admin, guru, dan guru BK. Seorang guru menjadi wali kelas
 * karena memegang kelas, dan guru piket hanya di hari jadwal piketnya.
 * Menu dan keterangan akun disusun dari tugas-tugas itu.
 */

const DASHBOARD = { href: '/dasbor', label: 'Dasbor' };
const CLASS_VIEW = { href: '/absen-kelas', label: 'Absen kelas' };
const DISPENSATIONS = {
    href: '/dispensasi',
    label: 'Dispensasi',
    badge: 'pendingDispensations',
};

const ADMIN_LINKS = [
    DASHBOARD,
    { href: '/pantauan', label: 'Pantauan' },
    { href: '/kelas', label: 'Kelas' },
    { href: '/siswa', label: 'Siswa' },
    { href: '/kartu', label: 'Kartu QR' },
    { href: '/tab-gerbang', label: 'Tab gerbang' },
    { href: '/jadwal-piket', label: 'Jadwal piket' },
    { href: '/izin', label: 'Izin & sakit' },
    DISPENSATIONS,
    { href: '/rekap', label: 'Rekap' },
    { href: '/siswa-berisiko', label: 'Siswa berisiko' },
    { href: '/pengguna', label: 'Pengguna' },
    { href: '/tahun-ajaran', label: 'Tahun ajaran' },
    { href: '/pengaturan', label: 'Pengaturan' },
];

const DUTY_LINKS = [
    { href: '/pantauan', label: 'Pantauan' },
    { href: '/terlambat', label: 'Absen piket' },
];

const HOMEROOM_LINKS = [
    { href: '/rekap', label: 'Rekap kelas' },
    { href: '/siswa-berisiko', label: 'Siswa berisiko' },
    { href: '/izin', label: 'Izin & sakit' },
];

export function navLinksFor(user) {
    if (user.role === 'admin') {
        return ADMIN_LINKS;
    }

    if (user.role === 'guru_bk') {
        return [DASHBOARD, { href: '/siswa-berisiko', label: 'Siswa berisiko' }, CLASS_VIEW];
    }

    return [DASHBOARD, ...(user.isOnDuty ? DUTY_LINKS : []), CLASS_VIEW, ...(user.homeroom ? HOMEROOM_LINKS : []), ...(user.homeroom || user.isOnDuty || user.hasDutySchedule ? [DISPENSATIONS] : [])];
}

/**
 * "Wali kelas VII-A · Piket hari ini" untuk guru, nama peran untuk yang lain.
 */
export function dutyLabel(user) {
    if (user.role !== 'guru') {
        return user.roleLabel;
    }

    const parts = [user.homeroom ? `Wali kelas ${user.homeroom}` : null, user.isOnDuty ? 'Piket hari ini' : null].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : 'Guru';
}
