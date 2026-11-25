import { Head, Link } from '@inertiajs/react';
import WeeklyScheduleForm from '@/Components/WeeklyScheduleForm';
import AppLayout from '@/Layouts/AppLayout';

export default function GreetingSchedule({ weekdays, teachers }) {
    return (
        <>
            <Head title="Jadwal sapa pagi" />

            <div className="page-head">
                <div>
                    <h6 className="text-muted">Master data</h6>
                    <h1>Jadwal sapa pagi</h1>
                </div>
            </div>

            <p className="text-muted" style={{ fontSize: 14, maxWidth: 760 }}>
                Jadwal yang sama setiap minggu. Di hari jadwalnya, guru sapa pagi mendapat menu Scan absen untuk mencatat siswa yang lupa membawa kartu, dari jam buka gerbang sampai jam masuk. Siswa yang datang setelah jam masuk tetap dicatat guru piket (lihat{' '}
                <Link href="/jadwal-piket">Jadwal piket</Link>). Akun guru dibuat di menu <Link href="/pengguna">Pengguna</Link>.
            </p>

            {teachers.length === 0 ? (
                <div className="empty">
                    <h4>Belum ada akun guru</h4>
                    <p className="text-muted">Buat akun guru dulu di menu Pengguna, lalu kembali ke sini.</p>
                </div>
            ) : (
                <WeeklyScheduleForm weekdays={weekdays} teachers={teachers} url="/jadwal-sapa-pagi" dutyLabel="Guru sapa pagi" />
            )}
        </>
    );
}

GreetingSchedule.layout = (page) => <AppLayout>{page}</AppLayout>;
