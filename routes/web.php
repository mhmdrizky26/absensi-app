<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AcademicYearActivationController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\CardPrintController;
use App\Http\Controllers\Admin\ClassroomAccessCodeController;
use App\Http\Controllers\Admin\ClassroomBatchController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\ClassroomStandardCodeController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentCardController;
use App\Http\Controllers\Admin\StudentCardRevocationController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClassScan\ClassLoginController;
use App\Http\Controllers\ClassScan\ClassSessionCloseController;
use App\Http\Controllers\ClassScan\ScanController;
use App\Http\Controllers\ClassScan\ScannerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Duty\LateScanController;
use App\Http\Controllers\Duty\MonitorClassroomController;
use App\Http\Controllers\Duty\MonitorController;
use App\Http\Controllers\Recap\AttendanceCorrectionController;
use App\Http\Controllers\Recap\EarlyWarningController;
use App\Http\Controllers\Recap\MarkHistoryController;
use App\Http\Controllers\Recap\MonthlyRecapController;
use App\Http\Controllers\Recap\RecapExportController;
use App\Http\Controllers\Recap\SchoolRecapController;
use App\Http\Controllers\Recap\SemesterRecapController;
use App\Http\Controllers\Staff\AttachmentController;
use App\Http\Controllers\Staff\DispensationController;
use App\Http\Controllers\Staff\ExcuseController;
use App\Http\Controllers\Staff\StudentSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'class.login'));

// Guru jam pertama: masuk dengan kode kelas, lalu scan dari HP.
Route::get('absen', [ClassLoginController::class, 'create'])->name('class.login');
Route::post('absen', [ClassLoginController::class, 'store'])->name('class.login.store');

Route::middleware('class.session')->prefix('absen')->group(function () {
    Route::get('kelas', [ScannerController::class, 'show'])->name('class.scanner');
    Route::post('kelas/scan', [ScanController::class, 'store'])->name('class.scan');
    Route::post('kelas/selesai', [ClassSessionCloseController::class, 'store'])->name('class.close');
    Route::post('keluar', [ClassLoginController::class, 'destroy'])->name('class.logout');
});

Route::middleware('guest')->group(function () {
    Route::get('masuk', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('masuk', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dasbor', DashboardController::class)->name('dashboard');

    // Profil untuk guru; admin mengatur akunnya sendiri lewat halaman Pengguna.
    Route::middleware('role:guru_piket,wali_kelas,guru_bk')->group(function () {
        Route::put('profil/username', [ProfileController::class, 'updateUsername'])->name('profile.username');
        Route::put('profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    Route::middleware('role:admin,guru_piket')->group(function () {
        Route::get('pantauan', [MonitorController::class, 'index'])->name('duty.monitor');
        Route::get('pantauan/kelas/{classroom}', [MonitorClassroomController::class, 'show'])->name('duty.monitor.classroom');
        Route::get('terlambat', [LateScanController::class, 'create'])->name('duty.late');
        Route::post('terlambat', [LateScanController::class, 'store'])->name('duty.late.store');
    });

    Route::middleware('role:admin,guru_piket,wali_kelas')->group(function () {
        Route::get('surat/{attendance}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('cari-siswa', [StudentSearchController::class, 'index'])->name('students.search');

        Route::get('dispensasi', [DispensationController::class, 'index'])->name('dispensations.index');
        Route::post('dispensasi', [DispensationController::class, 'store'])->name('dispensations.store');
        Route::post('dispensasi/{dispensation}/setujui', [DispensationController::class, 'approve'])->name('dispensations.approve');
        Route::post('dispensasi/{dispensation}/tolak', [DispensationController::class, 'reject'])->name('dispensations.reject');
        Route::delete('dispensasi/{dispensation}', [DispensationController::class, 'destroy'])->name('dispensations.destroy');
        Route::get('dispensasi/{dispensation}/surat', [DispensationController::class, 'attachment'])->name('dispensations.attachment');
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

    Route::middleware('role:admin,wali_kelas')->prefix('rekap')->group(function () {
        Route::get('/', [MonthlyRecapController::class, 'index'])->name('recap.monthly');
        Route::get('semester', [SemesterRecapController::class, 'index'])->name('recap.semester');
        Route::get('riwayat', [MarkHistoryController::class, 'show'])->name('recap.history');
        Route::post('koreksi', [AttendanceCorrectionController::class, 'store'])->name('recap.correct');
        Route::get('ekspor/bulanan', [RecapExportController::class, 'monthly'])->name('recap.export.monthly');
        Route::get('ekspor/semester', [RecapExportController::class, 'semester'])->name('recap.export.semester');
    });

    Route::middleware('role:admin')->prefix('rekap')->group(function () {
        Route::get('sekolah', [SchoolRecapController::class, 'index'])->name('recap.school');
        Route::get('ekspor/sekolah', [RecapExportController::class, 'school'])->name('recap.export.school');
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
        Route::put('kelas/{classroom}/kode', [ClassroomAccessCodeController::class, 'update'])->name('classrooms.access-code');
        Route::post('kelas/kode-standar', [ClassroomStandardCodeController::class, 'store'])->name('classrooms.standard-codes');
        Route::resource('kelas', ClassroomController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kelas' => 'classroom'])
            ->names('classrooms');

        Route::get('siswa/import', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('siswa/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('siswa/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::resource('siswa', StudentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['siswa' => 'student'])
            ->names('students');

        Route::get('kartu', [CardController::class, 'index'])->name('cards.index');
        Route::get('kartu/cetak', [CardPrintController::class, 'show'])->name('cards.print');
        Route::post('siswa/{student}/kartu', [StudentCardController::class, 'store'])->name('students.card.store');
        Route::post('siswa/{student}/kartu/cabut', [StudentCardRevocationController::class, 'store'])->name('students.card.revoke');
        Route::delete('siswa/{student}/kartu/cabut', [StudentCardRevocationController::class, 'destroy'])->name('students.card.restore');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::post('libur', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('libur/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::resource('pengguna', UserController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['pengguna' => 'user'])
            ->names('users');
    });
});
