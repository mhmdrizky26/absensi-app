import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarRange,
    ChartColumn,
    ClipboardList,
    FileDown,
    FilePen,
    ListChecks,
    QrCode,
    ScanLine,
    School,
    Tablet,
    UserCog,
    Users,
    CalendarX,
    TriangleAlert,
    MessageCircle,
} from 'lucide-react';
import TrendPanel from '@/Components/TrendPanel';
import AppLayout from '@/Layouts/AppLayout';

/**
 * Modul per peran. Modul tanpa href belum dikerjakan dan ditandai "Segera".
 */
const MODULES = {
    admin: [
        { icon: School, title: 'Kelas', body: 'Kelas VII–IX, wali kelas, dan kelas tanpa ruang.', href: '/kelas' },
        { icon: Tablet, title: 'Tab gerbang', body: 'Tab untuk scan di gerbang: kode login, status online, dan cabut tab yang hilang.', href: '/tab-gerbang' },
        { icon: Users, title: 'Siswa', body: 'Data siswa per kelas dan import dari Excel.', href: '/siswa' },
        { icon: UserCog, title: 'Pengguna', body: 'Akun admin, guru piket, wali kelas, dan guru BK.', href: '/pengguna' },
        { icon: CalendarRange, title: 'Tahun ajaran', body: 'Tahun ajaran aktif dan tanggal semester.', href: '/tahun-ajaran' },
        { icon: QrCode, title: 'Kartu QR', body: 'Cetak kartu QR massal per kelas, ganti kartu yang hilang.', href: '/kartu' },
        { icon: ListChecks, title: 'Pantauan hari ini', body: 'Siapa yang sudah dan belum absen, siswa terlambat, dan tombol selesaikan absen.', href: '/pantauan' },
        { icon: CalendarX, title: 'Jadwal & libur', body: 'Jam buka gerbang dan jam masuk per hari, serta kalender hari libur.', href: '/pengaturan' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Pantau pengajuan dispensasi dan persetujuannya.', href: '/dispensasi' },
        { icon: ChartColumn, title: 'Rekap sekolah', body: 'Kehadiran seluruh kelas per bulan dan semester, export Excel.', href: '/rekap/sekolah' },
        { icon: TriangleAlert, title: 'Siswa berisiko', body: 'Peringatan dini siswa yang sering alpa atau kehadirannya menurun.', href: '/siswa-berisiko' },
        { icon: MessageCircle, title: 'Notifikasi WhatsApp', body: 'Kabari orang tua siswa berisiko secara otomatis, lengkap dengan log pengiriman.', href: '/notifikasi-wa' },
    ],
    guru_bk: [
        { icon: TriangleAlert, title: 'Siswa berisiko', body: 'Peringatan dini: siswa yang sering alpa, alpa beruntun, atau kehadirannya menurun, lengkap dengan alasannya.', href: '/siswa-berisiko' },
    ],
    guru_piket: [
        { icon: ListChecks, title: 'Pantauan hari ini', body: 'Siapa yang sudah dan belum absen per kelas. Setelah jam masuk, tekan "Selesaikan absen" agar yang belum absen dicatat alpa.', href: '/pantauan' },
        { icon: ScanLine, title: 'Absen piket', body: 'Catat siswa yang lupa membawa kartu atau datang setelah gerbang ditutup.', href: '/terlambat' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Setujui dispensasi kegiatan sekolah bersama wali kelas.', href: '/dispensasi' },
    ],
    wali_kelas: [
        { icon: ClipboardList, title: 'Rekap kelas', body: 'Kehadiran kelas Anda dalam grid bulanan.', href: '/rekap' },
        { icon: TriangleAlert, title: 'Siswa berisiko', body: 'Siswa kelas Anda yang perlu didekati, beserta alasannya.', href: '/siswa-berisiko' },
        { icon: FilePen, title: 'Input izin & sakit', body: 'Catat surat izin atau sakit siswa kelas Anda.', href: '/izin' },
        { icon: BadgeCheck, title: 'Dispensasi', body: 'Ajukan dan setujui dispensasi siswa kelas Anda bersama guru piket.', href: '/dispensasi' },
        { icon: FileDown, title: 'Export untuk rapor', body: 'Rekap S/I/A per semester dalam Excel, atau cetak ke PDF.', href: '/rekap/semester' },
    ],
};

const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());

export default function Dashboard({ stats, trends }) {
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
                        <div className="stat-value">{stats.academicYear ? stats.academicYear.replace('/', '/​') : '—'}</div>
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

            {trends && <TrendPanel {...trends} />}
        </>
    );
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>;
