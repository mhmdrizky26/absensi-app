<?php

namespace App\Http\Controllers\Recap;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\RiskEpisode;
use App\Models\Student;
use App\Support\AttendanceRisk;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RiskFollowUpController extends Controller
{
    /**
     * Admin or guru BK marks a risky student as handled, with a short note.
     * The student moves to "Sedang dipantau" and comes back to the main list
     * only if things get worse.
     */
    public function store(Request $request, Student $student, AttendanceRisk $risk): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:300']], [], ['note' => 'Catatan tindak lanjut']);

        $episode = $this->episodeFor($student, $risk);

        if (! $episode) {
            return back()->with('error', "{$student->name} sedang tidak masuk daftar siswa berisiko.");
        }

        $episode->update(['handled_at' => now(), 'handled_by' => $request->user()->id, 'handled_note' => $validated['note']]);

        return back()->with('success', "{$student->name} ditandai sudah ditangani dan dipindah ke \"Sedang dipantau\".");
    }

    public function destroy(Student $student): RedirectResponse
    {
        RiskEpisode::query()->open()->where('student_id', $student->id)->update(['handled_at' => null, 'handled_by' => null, 'handled_note' => null]);

        return back()->with('success', "{$student->name} dikembalikan ke \"Perlu ditindaklanjuti\".");
    }

    /**
     * The student's current episode, created now if today's daily check has
     * not recorded them yet.
     */
    private function episodeFor(Student $student, AttendanceRisk $risk): ?RiskEpisode
    {
        $academicYear = AcademicYear::active();
        $classroom = $academicYear ? $student->classroomIn($academicYear) : null;

        if (! $classroom) {
            return null;
        }

        [$from, $to, $semester] = $academicYear->semesterSoFar(today());
        $row = collect($risk->flagged(new EloquentCollection([$classroom]), $from, $to))->firstWhere('id', $student->id);

        if (! $row) {
            return null;
        }

        return RiskEpisode::query()->open()->where('student_id', $student->id)->where('academic_year_id', $academicYear->id)->where('semester', $semester)->first()
            ?? RiskEpisode::create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'started_on' => today(),
                'level' => $row['level'],
                'reasons' => $row['reasons'],
                'streak_ended_on' => $row['streakEndedOn'],
            ]);
    }
}
