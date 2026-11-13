<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AcademicYearActivationController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\CardDesignController;
use App\Http\Controllers\Admin\CardPrintController;
use App\Http\Controllers\Admin\ClassroomBatchController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\ClassroomLodgingController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\Admin\DutyScheduleController;
use App\Http\Controllers\Admin\DutySubstitutionController;
use App\Http\Controllers\Admin\GateNetworkController;
use App\Http\Controllers\Admin\GateStationController;
use App\Http\Controllers\Admin\GateStationRevocationController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\OnlineDayController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentCardController;
use App\Http\Controllers\Admin\StudentCardRevocationController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentStudyRoomController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CodeLoginController;
use App\Http\Controllers\ClassView\ClassViewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Duty\FinalizeDayController;
use App\Http\Controllers\Duty\LateScanController;
use App\Http\Controllers\Duty\MonitorClassroomController;
use App\Http\Controllers\Duty\MonitorController;
use App\Http\Controllers\Gate\GateScanController;
use App\Http\Controllers\Gate\GateScannerController;
use App\Http\Controllers\LiveUpdateController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Recap\AttendanceCorrectionController;
use App\Http\Controllers\Recap\EarlyWarningController;
use App\Http\Controllers\Recap\GuardianContactController;
use App\Http\Controllers\Recap\MarkHistoryController;
use App\Http\Controllers\Recap\MonthlyRecapController;
use App\Http\Controllers\Recap\RecapExportController;
use App\Http\Controllers\Recap\RiskFollowUpController;
use App\Http\Controllers\Recap\RiskNoticeController;
use App\Http\Controllers\Recap\SchoolRecapController;
use App\Http\Controllers\Recap\SemesterRecapController;
use App\Http\Controllers\Staff\AttachmentController;
use App\Http\Controllers\Staff\DispensationController;
use App\Http\Controllers\Staff\ExcuseController;
use App\Http\Controllers\Staff\StudentSearchController;
use App\Http\Middleware\EnsureGateStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');

Route::get('/', fn (Request $request) => redirect()->route(match (true) {
    auth()->check() => 'dashboard',
    EnsureGateStation::stationFrom($request) !== null => 'gate.scanner',
    default => 'login',
}));

// Layar kode untuk tab gerbang: kode tab (TAB1) membuka scanner gerbang.
Route::get('kode', [CodeLoginController::class, 'create'])->name('code.login');
Route::post('kode', [CodeLoginController::class, 'store'])->name('code.login.store');

// Dulu guru mapel membuka kelas dengan kode kelas; sekarang lewat akunnya.
Route::redirect('lihat-kelas', 'absen-kelas');

// Tab di gerbang: login sekali dengan kode tab, lalu siswa scan sendiri.
Route::redirect('gerbang', 'kode');

Route::middleware('gate.station')->prefix('gerbang')->group(function () {
    Route::get('scan', [GateScannerController::class, 'show'])->name('gate.scanner');
    Route::post('status', [GateScannerController::class, 'status'])->name('gate.status');
    Route::post('scan', [GateScanController::class, 'store'])->name('gate.scan');
});

Route::middleware('guest')->group(function () {
    Route::get('masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dasbor', DashboardController::class)->name('dashboard');
    Route::get('pembaruan', LiveUpdateController::class)->name('live-updates');

    // Semua guru melihat absen hari ini per kelas (hanya lihat).
    Route::get('absen-kelas', [ClassViewController::class, 'show'])->name('class-view.show');

    // Profil untuk guru; admin mengatur akunnya sendiri lewat halaman Pengguna.
    Route::middleware('role:guru,guru_bk')->group(function () {
        Route::put('profil/username', [ProfileController::class, 'updateUsername'])->name('profile.username');
        Route::put('profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // Tugas piket: admin, dan guru piket hanya di hari jadwalnya.
    Route::middleware('role:admin,piket')->group(function () {
        Route::get('pantauan', [MonitorController::class, 'index'])->name('duty.monitor');
        Route::get('pantauan/kelas/{classroom}', [MonitorClassroomController::class, 'show'])->name('duty.monitor.classroom');
        Route::get('terlambat', [LateScanController::class, 'create'])->name('duty.late');
        Route::post('terlambat', [LateScanController::class, 'store'])->name('duty.late.store');
        Route::post('pantauan/selesaikan', [FinalizeDayController::class, 'store'])->name('duty.finalize');
        Route::delete('pantauan/selesaikan', [FinalizeDayController::class, 'destroy'])->name('duty.reopen');
    });

    Route::middleware('role:admin,piket,wali_kelas')->group(function () {
        Route::get('surat/{attendance}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('cari-siswa', [StudentSearchController::class, 'index'])->name('students.search');
    });

    // Guru yang punya jadwal piket boleh melihat semua dispensasi di hari lain, tanpa mengajukan atau menyetujui.
    Route::middleware('role:admin,piket,wali_kelas,jadwal_piket')->group(function () {
        Route::get('dispensasi', [DispensationController::class, 'index'])->name('dispensations.index');
        Route::get('dispensasi/{dispensation}/surat', [DispensationController::class, 'attachment'])->name('dispensations.attachment');
    });

    Route::middleware('role:admin,piket,wali_kelas')->group(function () {
        Route::post('dispensasi', [DispensationController::class, 'store'])->name('dispensations.store');
        Route::post('dispensasi/{dispensation}/setujui', [DispensationController::class, 'approve'])->name('dispensations.approve');
        Route::post('dispensasi/{dispensation}/tolak', [DispensationController::class, 'reject'])->name('dispensations.reject');
        Route::delete('dispensasi/{dispensation}', [DispensationController::class, 'destroy'])->name('dispensations.destroy');
    });

    // Izin & sakit dicatat admin dan wali kelas; guru piket melihatnya per kelas di Pantauan.
    Route::middleware('role:admin,wali_kelas')->group(function () {
        Route::get('izin', [ExcuseController::class, 'index'])->name('excuses.index');
        Route::post('izin', [ExcuseController::class, 'store'])->name('excuses.store');
    });

    // Peringatan dini: seluruh sekolah untuk admin dan guru BK, kelas sendiri untuk wali kelas.
    Route::middleware('role:admin,guru_bk,wali_kelas')->group(function () {
        Route::get('siswa-berisiko', [EarlyWarningController::class, 'index'])->name('early-warning');
        Route::get('siswa-berisiko/{student}', [EarlyWarningController::class, 'show'])->name('early-warning.student');
    });

    // Kirim pesan risiko ke orang tua sekarang juga: admin dan guru BK.
    Route::middleware('role:admin,guru_bk')->group(function () {
        Route::post('siswa-berisiko/{student}/kirim-wa', [RiskNoticeController::class, 'store'])->name('early-warning.notify');
        Route::post('siswa-berisiko/{student}/tindak-lanjut', [RiskFollowUpController::class, 'store'])->name('early-warning.follow-up');
        Route::delete('siswa-berisiko/{student}/tindak-lanjut', [RiskFollowUpController::class, 'destroy'])->name('early-warning.follow-up.destroy');
    });

    // Wali kelas menambahkan nomor orang tua siswa kelasnya dari halaman Rekap kelas (admin lewat menu Siswa).
    Route::middleware('role:wali_kelas')->post('siswa-berisiko/{student}/kontak', [GuardianContactController::class, 'store'])->name('early-warning.contact');

    Route::middleware('role:admin,wali_kelas')->prefix('rekap')->group(function () {
        Route::get('/', [MonthlyRecapController::class, 'index'])->name('recap.monthly');
        Route::get('semester', [SemesterRecapController::class, 'index'])->name('recap.semester');
        Route::get('riwayat', [MarkHistoryController::class, 'show'])->name('recap.history');
        Route::post('koreksi', [AttendanceCorrectionController::class, 'store'])->name('recap.correct');
        Route::get('ekspor/bulanan', [RecapExportController::class, 'monthly'])->name('recap.export.monthly');
        Route::get('ekspor/semester', [RecapExportController::class, 'semester'])->name('recap.export.semester');
        Route::get('ekspor/semester-lengkap', [RecapExportController::class, 'semesterWorkbook'])->name('recap.export.semester-workbook');
    });

    Route::middleware('role:admin')->prefix('rekap')->group(function () {
        Route::get('sekolah', [SchoolRecapController::class, 'index'])->name('recap.school');
        Route::get('ekspor/sekolah', [RecapExportController::class, 'school'])->name('recap.export.school');
        Route::get('ekspor/semua-kelas', [RecapExportController::class, 'schoolWorkbook'])->name('recap.export.school-workbook');
    });

    Route::middleware('role:admin')->group(function () {
        Route::resource('tahun-ajaran', AcademicYearController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['tahun-ajaran' => 'academicYear'])
            ->names('academic-years');
        Route::post('tahun-ajaran/{academicYear}/aktifkan', [AcademicYearActivationController::class, 'store'])
            ->name('academic-years.activate');
        Route::get('tahun-ajaran/{academicYear}/kenaikan-kelas', [PromotionController::class, 'show'])->name('academic-years.promotion');
        Route::post('tahun-ajaran/{academicYear}/kenaikan-kelas', [PromotionController::class, 'store'])->name('academic-years.promotion.store');
        Route::get('tahun-ajaran/{academicYear}/kenaikan-kelas/arsip', [PromotionController::class, 'archive'])->name('academic-years.promotion.archive');

        Route::post('kelas/massal', [ClassroomBatchController::class, 'store'])->name('classrooms.batch');
        Route::post('kelas/{classroom}/titipkan', [ClassroomLodgingController::class, 'store'])->name('classrooms.lodge');
        Route::delete('kelas/{classroom}/titipkan', [ClassroomLodgingController::class, 'destroy'])->name('classrooms.lodge.destroy');
        Route::resource('kelas', ClassroomController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kelas' => 'classroom'])
            ->names('classrooms');

        Route::get('siswa/import', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('siswa/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('siswa/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::get('siswa/ekspor', [DataExportController::class, 'students'])->name('students.export');
        Route::resource('siswa', StudentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['siswa' => 'student'])
            ->names('students');

        Route::get('kartu', [CardController::class, 'index'])->name('cards.index');
        Route::get('kartu/cetak', [CardPrintController::class, 'show'])->name('cards.print');
        Route::get('kartu/desain', [CardDesignController::class, 'edit'])->name('cards.design');
        Route::put('kartu/desain', [CardDesignController::class, 'update'])->name('cards.design.update');
        Route::get('kartu/desain/latar', [CardDesignController::class, 'background'])->name('cards.design.background');
        Route::post('kartu/desain/latar', [CardDesignController::class, 'storeBackground'])->name('cards.design.background.store');
        Route::delete('kartu/desain/latar', [CardDesignController::class, 'destroyBackground'])->name('cards.design.background.destroy');
        Route::post('siswa/{student}/kartu', [StudentCardController::class, 'store'])->name('students.card.store');
        Route::put('siswa/{student}/ruang-belajar', [StudentStudyRoomController::class, 'update'])->name('students.study-room');
        Route::post('siswa/{student}/kartu/cabut', [StudentCardRevocationController::class, 'store'])->name('students.card.revoke');
        Route::delete('siswa/{student}/kartu/cabut', [StudentCardRevocationController::class, 'destroy'])->name('students.card.restore');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::put('pengaturan/risiko', [SettingController::class, 'updateRisk'])->name('settings.risk');
        Route::put('pengaturan/umum', [SettingController::class, 'updateGeneral'])->name('settings.general');
        Route::get('notifikasi-wa', [WhatsAppController::class, 'index'])->name('whatsapp.index');
        Route::put('notifikasi-wa', [WhatsAppController::class, 'update'])->name('whatsapp.update');
        Route::put('notifikasi-wa/pesan', [WhatsAppController::class, 'updateTexts'])->name('whatsapp.texts');
        Route::put('notifikasi-wa/token', [WhatsAppController::class, 'updateToken'])->name('whatsapp.token');
        Route::post('notifikasi-wa/uji', [WhatsAppController::class, 'test'])->name('whatsapp.test');
        Route::post('notifikasi-wa/jalankan', [WhatsAppController::class, 'run'])->name('whatsapp.run');
        Route::post('notifikasi-wa/pesan/{riskMessage}/kirim-ulang', [WhatsAppController::class, 'resend'])->name('whatsapp.resend');
        Route::resource('tab-gerbang', GateStationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['tab-gerbang' => 'gateStation'])
            ->names('gate-stations');
        Route::post('tab-gerbang/{gateStation}/cabut', [GateStationRevocationController::class, 'store'])->name('gate-stations.revoke');
        Route::delete('tab-gerbang/{gateStation}/cabut', [GateStationRevocationController::class, 'destroy'])->name('gate-stations.restore');
        Route::put('jaringan-gerbang', [GateNetworkController::class, 'update'])->name('gate-network.update');
        Route::post('libur', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('libur/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::post('daring', [OnlineDayController::class, 'store'])->name('online-days.store');
        Route::delete('daring/{onlineDay}', [OnlineDayController::class, 'destroy'])->name('online-days.destroy');

        Route::get('jadwal-piket', [DutyScheduleController::class, 'index'])->name('duty-schedule.index');
        Route::put('jadwal-piket', [DutyScheduleController::class, 'update'])->name('duty-schedule.update');
        Route::post('jadwal-piket/pengganti', [DutySubstitutionController::class, 'store'])->name('duty-substitutions.store');
        Route::delete('jadwal-piket/pengganti/{dutySubstitution}', [DutySubstitutionController::class, 'destroy'])->name('duty-substitutions.destroy');

        Route::get('pengguna/import', [UserImportController::class, 'create'])->name('users.import');
        Route::post('pengguna/import', [UserImportController::class, 'store'])->name('users.import.store');
        Route::get('pengguna/import/template', [UserImportController::class, 'template'])->name('users.import.template');
        Route::get('pengguna/import/akun', [UserImportController::class, 'accounts'])->name('users.import.accounts');
        Route::delete('pengguna/import/akun', [UserImportController::class, 'forgetAccounts'])->name('users.import.accounts.forget');
        Route::resource('pengguna', UserController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['pengguna' => 'user'])
            ->names('users');
    });
});
