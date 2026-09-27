<?php

namespace App\Http\Controllers\Staff;

use App\Actions\SetAttendanceStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ExcuseRequest;
use App\Models\Attendance;
use App\Models\Student;
use App\Support\AttendanceWindow;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcuseController extends Controller
{
    /**
     * Recent and upcoming Sakit/Izin marks, with the form to add one.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $homeroom = $user->hasRole(Role::WaliKelas) ? $user->currentHomeroom() : null;

        $excuses = Attendance::query()
            ->with(['student:id,name,nis', 'classroom:id,name', 'recorder:id,name'])
            ->whereIn('status', [AttendanceStatus::Sick, AttendanceStatus::Excused])
            ->whereDate('date', '>=', today()->subDays(7))
            ->when($user->hasRole(Role::WaliKelas), fn (Builder $query) => $query->where('classroom_id', $homeroom?->id ?? 0))
            ->orderByDesc('date')
            ->latest('recorded_at')
            ->limit(150)
            ->get()
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'date' => $attendance->date->toDateString(),
                'status' => $attendance->status->value,
                'statusLabel' => $attendance->status->label(),
                'name' => $attendance->student->name,
                'nis' => $attendance->student->nis,
                'classroom' => $attendance->classroom->name,
                'note' => $attendance->note,
                'recorder' => $attendance->recorder?->name,
                'hasAttachment' => $attendance->attachment_path !== null,
            ]);

        return Inertia::render('Staff/Excuses', [
            'excuses' => $excuses,
            'homeroom' => $homeroom?->name,
            'isHomeroomTeacher' => $user->hasRole(Role::WaliKelas),
        ]);
    }

    /**
     * Mark a student Sakit or Izin for every school day in the range. Days the
     * student was already at school are left alone.
     */
    public function store(ExcuseRequest $request, AttendanceWindow $window, SetAttendanceStatus $setAttendanceStatus): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $status = AttendanceStatus::from($request->string('status')->value());
        $attachmentPath = $request->file('attachment')?->store('surat', 'local');

        $marked = [];
        $skippedPresent = 0;

        foreach (CarbonPeriod::create($request->date('from'), $request->date('to')) as $date) {
            if (! $window->isSchoolDay($date)) {
                continue;
            }

            $existing = Attendance::query()->where('student_id', $student->id)->whereDate('date', $date)->first();

            if ($existing?->status->isAtSchool()) {
                $skippedPresent++;

                continue;
            }

            $setAttendanceStatus->handle($student, $date, $status, AttendanceSource::Staff, $request->user(), [
                'note' => $request->string('note')->value(),
                'attachment_path' => $attachmentPath,
            ]);
            $marked[] = $date->translatedFormat('j M');
        }

        if ($marked === []) {
            return back()->with('error', "Tidak ada hari yang dicatat untuk {$student->name}: rentang tanggal itu libur atau siswa sudah hadir.");
        }

        $message = "{$student->name} dicatat {$status->label()} untuk ".count($marked).' hari ('.implode(', ', $marked).').';

        if ($skippedPresent > 0) {
            $message .= " {$skippedPresent} hari dilewati karena siswa hadir.";
        }

        return back()->with('success', $message);
    }
}
