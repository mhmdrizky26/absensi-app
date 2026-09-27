import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarRange,
    ChartColumn,
    ClipboardList,
    ClockAlert,
    FileDown,
    FilePen,
    ListChecks,
    QrCode,
    ScanLine,
    School,
    UserCog,
    Users,
    CalendarX,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';

/**
 * Modul per peran. Modul tanpa href belum dikerjakan dan ditandai "Segera".
 */
const MODULES = {
    admin: [
        { icon: School, title: 'Kelas & kode kelas', body: 'Kelas VII–IX, wali kelas, dan kode kelas untuk scan.', href: '/kelas' },
        { icon: Users, title: 'Siswa', body: 'Data siswa per kelas dan import dari Excel.', href: '/siswa' },
        { icon: UserCog, title: 'Pengguna', body: 'Akun admin, guru piket, dan wali kelas.', href: '/pengguna' },
        { icon: CalendarRange, title: 'Tahun ajaran', body: 'Tahun ajaran aktif dan tanggal semester.', href: '/tahun-ajaran' },
        { icon: QrCode, title: 'Kartu QR', body: 'Cetak kartu QR massal per kelas, ganti kartu yang hilang.', href: '/kartu' },
        { icon: ListChecks, title: 'Pantauan hari ini', body: 'Kelas yang sudah dan belum absen, siswa alpa dan terlambat.', href: '/pantauan' },
        { icon: CalendarX, title: 'Jam absensi & libur', body: 'Jam buka-tutup scan dan kalender hari libur.', href: '/pengaturan' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Pantau pengajuan dispensasi dan persetujuannya.', href: '/dispensasi' },
        { icon: ChartColumn, title: 'Rekap sekolah', body: 'Kehadiran seluruh kelas per bulan dan semester, export Excel.', href: '/rekap/sekolah' },
    ],
    guru_piket: [
        { icon: ListChecks, title: 'Pantauan hari ini', body: 'Kelas yang sudah dan belum absen, siswa alpa hari ini.', href: '/pantauan' },
        { icon: ScanLine, title: 'Scan terlambat', body: 'Scan kartu siswa yang datang setelah sesi kelas ditutup.', href: '/terlambat' },
        { icon: FilePen, title: 'Input izin & sakit', body: 'Catat surat izin atau sakit yang dititipkan orang tua.', href: '/izin' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Setujui dispensasi kegiatan sekolah bersama wali kelas.', href: '/dispensasi' },
    ],
    wali_kelas: [
        { icon: ClipboardList, title: 'Rekap kelas', body: 'Kehadiran kelas Anda dalam grid bulanan.', href: '/rekap' },
        { icon: FilePen, title: 'Input izin & sakit', body: 'Catat surat izin atau sakit siswa kelas Anda.', href: '/izin' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Ajukan dan setujui dispensasi siswa kelas Anda bersama guru piket.', href: '/dispensasi' },
        { icon: ClockAlert, title: 'Koreksi status', body: 'Klik sel di rekap bulanan untuk mengubah status, lengkap dengan alasan.', href: '/rekap' },
        { icon: FileDown, title: 'Export untuk rapor', body: 'Rekap S/I/A per semester dalam Excel, atau cetak ke PDF.', href: '/rekap/semester' },
    ],
};

const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());

export default function Dashboard({ stats }) {
    const { auth } = usePage().props;
    const modules = MODULES[auth.user.role] ?? [];

    return (
        <>
            <Head title="Dasbor" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">{auth.user.roleLabel}</h6>
                    <h1>Halo, {auth.user.name}</h1>
                </div>
                <div className="text-muted" style={{ fontSize: 14 }}>
                    {today}
                </div>
            </div>

            {stats && (
                <div className="stat-strip">
                    <div className="stat">
                        <div className="stat-label">Tahun ajaran aktif</div>
                        <div className="stat-value">{stats.academicYear ?? '—'}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-label">Kelas</div>
                        <div className="stat-value">{stats.classrooms}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-label">Siswa aktif</div>
                        <div className="stat-value">{stats.students.toLocaleString('id-ID')}</div>
                    </div>
                    <div className="stat">
                        <div className="stat-label">Akun aktif</div>
                        <div className="stat-value">{stats.users}</div>
                    </div>
                </div>
            )}

            <h4>Menu Anda</h4>
            <div className="module-grid">
                {modules.map(({ icon: Icon, title, body, href }) => {
                    const content = (
                        <>
                            <Icon size={22} aria-hidden="true" />
                            <div className="module-title">{title}</div>
                            <p className="module-body">{body}</p>
                            {!href && (
                                <span>
                                    <span className="tag tag-neutral">Segera</span>
                                </span>
                            )}
                        </>
                    );

                    return href ? (
                        <Link key={title} href={href} className="module">
                            {content}
                        </Link>
                    ) : (
                        <div key={title} className="module">
                            {content}
                        </div>
                    );
                })}
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>;
